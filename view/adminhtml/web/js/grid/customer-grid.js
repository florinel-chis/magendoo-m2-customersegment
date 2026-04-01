/**
 * Magendoo CustomerSegment Customer Grid Component
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

define([
    'Magento_Ui/js/grid/grid',
    'Magento_Ui/js/modal/alert'
], function (Grid, alert) {
    'use strict';

    return Grid.extend({
        defaults: {
            template: 'Magendoo_CustomerSegment/grid/customer-grid',
            listingConfig: {
                component: 'Magento_Ui/js/grid/listing',
                name: '${$.name}_listing',
                dataScope: '${$.name}_listing'
            },
            imports: {
                segmentId: '${$.provider}:data.segment.segment_id'
            },
            listens: {
                segmentId: 'onSegmentIdChange'
            }
        },

        /**
         * Initialize component
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            
            if (this.segmentId) {
                this.onSegmentIdChange(this.segmentId);
            }

            return this;
        },

        /**
         * Handle segment ID change
         *
         * @param {number} segmentId
         */
        onSegmentIdChange: function (segmentId) {
            if (!segmentId) {
                return;
            }

            this.params.segment_id = segmentId;
            this.reload();
        }
    });
});
