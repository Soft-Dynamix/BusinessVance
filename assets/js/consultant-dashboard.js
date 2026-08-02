/**
 * BusinessVance Consultant Dashboard - Frontend JavaScript
 * @since 2.5.0 Added questionnaire CSV download
 */
(function($) {
    'use strict';

    /**
     * Download questionnaire responses as CSV for a project.
     */
    window.bv_cd_download_questionnaire = function(projectId) {
        var url = bv_cd.ajax_url
            + '?action=bv_cd_download_questionnaire'
            + '&nonce=' + bv_cd.nonce
            + '&project_id=' + encodeURIComponent(projectId);
        window.location.href = url;
    };

    console.log('BV Consultant Dashboard loaded');
})(jQuery);
