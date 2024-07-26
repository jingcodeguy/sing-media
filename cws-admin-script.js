jQuery(document).ready(function($) {
    // Handle individual media item action
    $('a.cws-compare-webp-size').on('click', function(e) {
        e.preventDefault();

        var attachmentId = $(this).data('id');
        var resultDiv = $('#cws-result-' + attachmentId);

        // If the result div doesn't exist, create it
        if (resultDiv.length === 0) {
            resultDiv = $('<div class="cws-result" id="cws-result-' + attachmentId + '"></div>').insertAfter($(this));
        } else {
            resultDiv.empty(); // Clear previous messages
        }

        resultDiv.html('<p>Processing...</p>');

        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'cws_compare_webp_size',
                attachment_id: attachmentId
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<p>' + response.data + '</p>');
                } else {
                    resultDiv.html('<p>Error: ' + response.data + '</p>');
                }
            }
        });
    });
});
