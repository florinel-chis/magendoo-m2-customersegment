<?php
/**
 * Magendoo CustomerSegment - Segment condition (de)serialization unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model;

use Magendoo\CustomerSegment\Model\Condition\Combine;
use Magendoo\CustomerSegment\Model\Condition\CombineFactory;
use Magendoo\CustomerSegment\Model\Segment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers the admin-form condition round-trip: flat POST -> recursive tree -> canonical serialized shape.
 *
 * The canonical shape nests children under the literal `conditions` key (contract C1); the previous
 * implementation stored children under numeric position keys, which produced an empty condition tree
 * on load and made every saved segment match ALL customers.
 */
#[CoversClass(Segment::class)]
class SegmentSerializationTest extends TestCase
{
    private const CUSTOMER_TYPE = \Magendoo\CustomerSegment\Model\Condition\Customer::class;
    private const COMBINE_TYPE = Combine::class;

    /** @var CombineFactory&MockObject */
    private $combineFactory;

    /** @var LoggerInterface&MockObject */
    private $logger;

    protected function setUp(): void
    {
        $this->combineFactory = $this->createMock(CombineFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Build a Segment without invoking Magento's model constructor (which needs a live ObjectManager).
     *
     * convertFlatToRecursive()/loadPost() only rely on the injected combineFactory + logger and the
     * DataObject `_data` bag, so those are wired directly by reflection.
     */
    private function newSegment(): Segment
    {
        $segment = (new \ReflectionClass(Segment::class))->newInstanceWithoutConstructor();

        foreach (['combineFactory' => $this->combineFactory, 'logger' => $this->logger] as $name => $value) {
            $property = new \ReflectionProperty(Segment::class, $name);
            $property->setAccessible(true);
            $property->setValue($segment, $value);
        }

        return $segment;
    }

    /**
     * Invoke the protected convertFlatToRecursive() with the given flat form data.
     */
    private function convert(Segment $segment, array $flat): ?array
    {
        $method = new \ReflectionMethod($segment, 'convertFlatToRecursive');
        $method->setAccessible(true);

        return $method->invoke($segment, $flat);
    }

    // ==================== convertFlatToRecursive() ====================

    public function testConvertReturnsNullWhenNoRootConditionPosted(): void
    {
        $segment = $this->newSegment();

        // No key is rooted at position `1` — the root node (`$arr['conditions']['1']`) is never created.
        $result = $this->convert($segment, [
            '2--1' => ['type' => self::CUSTOMER_TYPE, 'attribute' => 'email'],
        ]);

        $this->assertNull($result);
    }

    public function testConvertReturnsNullForEmptyPost(): void
    {
        $segment = $this->newSegment();
        $this->assertNull($this->convert($segment, []));
    }

    public function testConvertReturnsRootOnlyNodeWithoutChildren(): void
    {
        $segment = $this->newSegment();

        $result = $this->convert($segment, [
            '1' => ['type' => self::COMBINE_TYPE, 'aggregator' => 'all', 'value' => '1'],
        ]);

        $this->assertIsArray($result);
        $this->assertSame(self::COMBINE_TYPE, $result['type']);
        $this->assertSame('all', $result['aggregator']);
        // No children posted -> no conditions key at the root.
        $this->assertArrayNotHasKey('conditions', $result);
    }

    public function testConvertNestsChildUnderConditionsKeyNotNumericKey(): void
    {
        $segment = $this->newSegment();

        $result = $this->convert($segment, [
            '1' => ['type' => self::COMBINE_TYPE, 'aggregator' => 'all', 'value' => '1'],
            '1--1' => [
                'type' => self::CUSTOMER_TYPE,
                'attribute' => 'email',
                'operator' => '{}',
                'value' => 'test.com',
            ],
        ]);

        // Root is the combine node.
        $this->assertSame(self::COMBINE_TYPE, $result['type']);

        // The critical contract: the child lives under `conditions`, NOT under a numeric root key.
        $this->assertArrayHasKey('conditions', $result);
        $this->assertArrayNotHasKey('1', $result);

        $child = $result['conditions']['1'];
        $this->assertSame(self::CUSTOMER_TYPE, $child['type']);
        $this->assertSame('email', $child['attribute']);
        $this->assertSame('{}', $child['operator']);
        $this->assertSame('test.com', $child['value']);
    }

    public function testConvertBuildsDeeplyNestedConditionsTree(): void
    {
        $segment = $this->newSegment();

        $result = $this->convert($segment, [
            '1' => ['type' => self::COMBINE_TYPE, 'aggregator' => 'all', 'value' => '1'],
            '1--1' => ['type' => self::CUSTOMER_TYPE, 'attribute' => 'email', 'operator' => '{}', 'value' => 'a'],
            '1--2' => ['type' => self::COMBINE_TYPE, 'aggregator' => 'any', 'value' => '1'],
            '1--2--1' => ['type' => self::CUSTOMER_TYPE, 'attribute' => 'group_id', 'operator' => '==', 'value' => '1'],
        ]);

        $this->assertSame('a', $result['conditions']['1']['value']);

        $nested = $result['conditions']['2'];
        $this->assertSame(self::COMBINE_TYPE, $nested['type']);
        $this->assertSame('any', $nested['aggregator']);

        // The grandchild nests one further level under its own `conditions` key.
        $grandchild = $nested['conditions']['1'];
        $this->assertSame('group_id', $grandchild['attribute']);
    }

    public function testConvertIgnoresNonArrayValues(): void
    {
        $segment = $this->newSegment();

        $result = $this->convert($segment, [
            '1' => ['type' => self::COMBINE_TYPE, 'aggregator' => 'all', 'value' => '1'],
            'new_child' => '',
        ]);

        $this->assertSame(self::COMBINE_TYPE, $result['type']);
        $this->assertArrayNotHasKey('conditions', $result);
    }

    // ==================== loadPost() ====================

    public function testLoadPostLeavesTreeUntouchedWhenConditionsKeyAbsent(): void
    {
        $segment = $this->newSegment();
        $segment->setConditionsSerialized('{"preserved":true}');

        // No `conditions` key in the POST -> the stored tree must be preserved and no combine built.
        $this->combineFactory->expects($this->never())->method('create');

        $returned = $segment->loadPost(['name' => 'Just a name change']);

        $this->assertSame($segment, $returned);
        $this->assertSame('{"preserved":true}', $segment->getConditionsSerialized());
    }

    public function testLoadPostRebuildsTreeAndSerializesCanonicalShape(): void
    {
        $segment = $this->newSegment();

        $canonical = [
            'type' => self::COMBINE_TYPE,
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [
                [
                    'type' => self::CUSTOMER_TYPE,
                    'attribute' => 'email',
                    'operator' => '{}',
                    'value' => 'test.com',
                ],
            ],
        ];

        $capturedLoadArray = null;
        $combine = $this->createMock(Combine::class);
        $combine->expects($this->once())
            ->method('loadArray')
            ->willReturnCallback(function (array $arr) use (&$capturedLoadArray, $combine) {
                $capturedLoadArray = $arr;
                return $combine;
            });
        $combine->method('asArray')->willReturn($canonical);
        $this->combineFactory->method('create')->willReturn($combine);

        $segment->loadPost([
            'conditions' => [
                '1' => ['type' => self::COMBINE_TYPE, 'aggregator' => 'all', 'value' => '1'],
                '1--1' => [
                    'type' => self::CUSTOMER_TYPE,
                    'attribute' => 'email',
                    'operator' => '{}',
                    'value' => 'test.com',
                ],
            ],
        ]);

        // loadArray received the recursive tree with the child under `conditions`.
        $this->assertIsArray($capturedLoadArray);
        $this->assertSame('email', $capturedLoadArray['conditions']['1']['attribute']);

        // The serialized output is the canonical shape (children under an ordered `conditions` list).
        $stored = json_decode((string) $segment->getConditionsSerialized(), true);
        $this->assertSame(self::COMBINE_TYPE, $stored['type']);
        $this->assertArrayHasKey('conditions', $stored);
        $this->assertSame('test.com', $stored['conditions'][0]['value']);
    }

    public function testLoadPostWithEmptyConditionsClearsTreeToEmptyCombine(): void
    {
        $segment = $this->newSegment();

        $emptyCanonical = [
            'type' => self::COMBINE_TYPE,
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [],
        ];

        $combine = $this->createMock(Combine::class);
        // With no root condition posted, loadArray must NOT be called; an empty combine is built instead.
        $combine->expects($this->never())->method('loadArray');
        $combine->expects($this->once())->method('setConditions')->with([]);
        $combine->method('asArray')->willReturn($emptyCanonical);
        $this->combineFactory->method('create')->willReturn($combine);

        $segment->loadPost(['conditions' => []]);

        $stored = json_decode((string) $segment->getConditionsSerialized(), true);
        $this->assertSame([], $stored['conditions']);
    }
}
