jQuery(document).ready(function($) {
    let currentPage = 1;
    let itemsPerPage = 20;
    let allData = [];

    // Initialize datepicker
    $('#date-from, #date-to').datepicker({
        dateFormat: 'mm/dd/yy',
        changeMonth: true,
        changeYear: true,
        showAnim: 'fadeIn',
        yearRange: '-10:+0',
        beforeShow: function(input, inst) {
            inst.dpDiv.css({
                marginTop: '5px',
                backgroundColor: 'white'
            });
        }
    });

    // Event Listeners
    $('#apply-filters').on('click', function() {
        loadReportData();
    });

    $('#reset-filters').on('click', function() {
        $('#date-from, #date-to').val('');
        $('#scan-type-filter').val('');
        $('#items-per-page').val('20');
        $('#search-input').val('');
        allData = [];
        updateReportTable([]);
    });

    $('#items-per-page').on('change', function() {
        itemsPerPage = parseInt($(this).val());
        currentPage = 1;
        if(allData.length > 0) {
            displayCurrentPage();
        }
    });

    $('#prev-page').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            displayCurrentPage();
        }
    });

    $('#next-page').on('click', function() {
        const totalPages = Math.ceil(allData.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            displayCurrentPage();
        }
    });

    $('#export-excel').on('click', function(e) {
        e.preventDefault();
        
        // Build the URL with parameters
        const params = new URLSearchParams({
            action: 'trm_export_scan_report',
            date_from: $('#date-from').val(),
            date_to: $('#date-to').val(),
            scan_type: $('#scan-type-filter').val(),
            nonce: trmQrVars.nonce
        });

        // Redirect to download URL
        window.location.href = `${trmQrVars.ajaxurl}?${params.toString()}`;
    });

    let searchTimeout;
    $('#search-input').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            filterAndDisplayData();
        }, 300);
    });

    function filterAndDisplayData() {
        const searchTerm = $('#search-input').val().toLowerCase();
        let filteredData = allData;
        
        if (searchTerm) {
            filteredData = allData.filter(row => {
                return (
                    row.type.toLowerCase().includes(searchTerm) ||
                    row.user_email.toLowerCase().includes(searchTerm) ||
                    row.user_data.first_name.toLowerCase().includes(searchTerm) ||
                    row.user_data.last_name.toLowerCase().includes(searchTerm) ||
                    row.user_data.phone.toLowerCase().includes(searchTerm) ||
                    row.scan_time.toLowerCase().includes(searchTerm)
                );
            });
        }
        
        currentPage = 1;
        if(filteredData.length > 0) {
            updateReportTable(filteredData.slice(0, itemsPerPage));
            $('.pagination-controls').show();
        } else {
            updateReportTable([]);
            $('.pagination-controls').hide();
        }
    }

    function loadReportData() {
        $('.table-responsive').addClass('loading');
        
        $.ajax({
            url: trmQrVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'trm_get_scan_report',
                date_from: $('#date-from').val(),
                date_to: $('#date-to').val(),
                scan_type: $('#scan-type-filter').val(),
                nonce: trmQrVars.nonce
            },
            success: function(response) {
                if(response.success) {
                    allData = response.data;
                    currentPage = 1;
                    
                    if(allData.length > 0) {
                        $('.table-responsive, .pagination-controls').show();
                        displayCurrentPage();
                    } else {
                        $('.table-responsive').show();
                        $('.pagination-controls').hide();
                        updateReportTable([]);
                    }
                } else {
                    alert('Error loading data. Please try again.');
                }
                $('.table-responsive').removeClass('loading');
            },
            error: function() {
                alert('Error loading data. Please try again.');
                $('.table-responsive').removeClass('loading');
            }
        });
    }

    function displayCurrentPage() {
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageData = allData.slice(startIndex, endIndex);
        
        const totalPages = Math.ceil(allData.length / itemsPerPage);
        $('#current-page').text(currentPage);
        $('#total-pages').text(totalPages);
        
        $('#prev-page').prop('disabled', currentPage === 1);
        $('#next-page').prop('disabled', currentPage === totalPages);
        
        updateReportTable(pageData);
    }

    function updateReportTable(data) {
        const tbody = $('#scan-report-data');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.append('<tr><td colspan="6" class="no-data">No data found for the selected criteria</td></tr>');
            return;
        }
        
        data.forEach(function(row) {
            const tr = `
                <tr>
                    <td>${escapeHtml(row.type)}</td>
                    <td>${escapeHtml(row.user_email)}</td>
                    <td>${escapeHtml(row.user_data.first_name)}</td>
                    <td>${escapeHtml(row.user_data.last_name)}</td>
                    <td>${escapeHtml(row.user_data.phone)}</td>
                    <td>${escapeHtml(row.scan_time)}</td>
                </tr>`;
            tbody.append(tr);
        });
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) {
            return '';
        }
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}); 