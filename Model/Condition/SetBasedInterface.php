<?php
/**
 * Magendoo CustomerSegment set-based condition contract
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

/**
 * Implemented by conditions that can resolve their matching customers with a single set-based query
 * instead of per-customer validation.
 *
 * Contract (pinned cross-package — see docs/module-lifecycle/customersegment-run/BRIEF.md):
 *  - getMatchingCustomerIds() returns the FULL set of customer entity IDs that satisfy this condition,
 *    as an array of ints (possibly empty), OR null when this condition/operator cannot be evaluated
 *    set-based (the caller must then fall back to per-customer validate()).
 *  - The set returned MUST be consistent with validate(): for every customer id C,
 *    (in_array(C, getMatchingCustomerIds())) === validate(C). A condition that cannot guarantee this
 *    for a given operator/attribute MUST return null rather than a partial/incorrect set.
 */
interface SetBasedInterface
{
    /**
     * Full set of customer IDs matching this condition, or null if not resolvable set-based.
     *
     * @return int[]|null
     */
    public function getMatchingCustomerIds(): ?array;
}
