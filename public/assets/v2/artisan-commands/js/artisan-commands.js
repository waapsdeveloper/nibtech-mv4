/**
 * V2 Artisan Commands JavaScript
 * Handles artisan commands page functionality including migrations, command execution, and job management
 * Uses jQuery for cleaner and more efficient DOM manipulation
 */

(function($) {
    'use strict';

    // Get URLs from global config (set in Blade template)
    const urls = window.ArtisanCommandsConfig?.urls || {};
    const getUrl = (key) => urls[key] || '';

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // Get CSRF token
    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    // Handle run migrations button
    $(document).ready(function() {
        $('#runMigrationsBtn').on('click', function() {
            const $btn = $(this);
            const $outputContainer = $('.migration-output-container');
            const $outputDiv = $('#migrationOutput');
            
            if ($outputContainer.length && $outputDiv.length) {
                $outputContainer.show();
                $outputDiv.show().html('<div class="text-info">Running migrations...</div>');
            }

            // Disable button
            $btn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Running...');

            $.ajax({
                url: getUrl('runMigrations'),
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            })
            .done(function(data) {
                if (data.success) {
                    if (data.status === 'queued') {
                        $outputDiv.html('<div class="alert alert-info mb-2">' +
                                     '<i class="fe fe-clock me-2"></i><strong>Migration command queued successfully!</strong><br>' +
                                     '<small class="text-muted">The migration is running in the background. Check the logs for output:<br>' +
                                     '<code>tail -f storage/logs/laravel.log | grep "ExecuteArtisanCommandJob"</code><br><br>' +
                                     '<strong>Note:</strong> Make sure your queue worker is running:<br>' +
                                     '<code>php artisan queue:work</code> or <code>php artisan queue:listen</code><br><br>' +
                                     'After migrations complete, refresh this page to see updated status.' +
                                     '</small></div>');
                    } else {
                        $outputDiv.html('<div class="text-success mb-2">✓ Migrations executed successfully</div>' +
                                     '<pre class="mb-0">' + escapeHtml(data.output || 'No output') + '</pre>');
                    }
                } else {
                    $outputDiv.html('<div class="text-danger mb-2">✗ Migration failed</div>' +
                                 '<pre class="mb-0 text-danger">' + escapeHtml(data.error || 'Unknown error') + '</pre>');
                }
                
                // Re-enable button
                $btn.prop('disabled', false).html('<i class="fe fe-play me-1"></i>Run Pending Migrations');
            })
            .fail(function(xhr) {
                const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
                $outputDiv.html('<div class="text-danger">Error: ' + escapeHtml(error) + '</div>');
                
                // Re-enable button
                $btn.prop('disabled', false).html('<i class="fe fe-play me-1"></i>Run Pending Migrations');
            });
        });
    });

    // Record migration in database
    window.recordMigration = function(migrationName) {
        if (!confirm('Are you sure you want to record this migration in the database? This will mark it as completed.')) {
            return;
        }

        const $contentDiv = $('#migrationDetailsContent');
        const originalContent = $contentDiv.html();
        
        $contentDiv.html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Recording migration...</div></div>');

        $.ajax({
            url: getUrl('recordMigration'),
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            data: JSON.stringify({
                migration: migrationName
            }),
            contentType: 'application/json'
        })
        .done(function(data) {
            if (data.success) {
                $contentDiv.html('<div class="alert alert-success mb-3">' +
                              '<i class="fe fe-check-circle me-2"></i><strong>Success!</strong> ' +
                              'Migration <code>' + escapeHtml(migrationName) + '</code> has been recorded in the database (Batch: ' + data.migration.batch + ').' +
                              '</div>' +
                              '<div class="alert alert-info mb-0">' +
                              'Please refresh the page to see the updated migration status.' +
                              '</div>');
                
                // Optionally auto-refresh after 2 seconds
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                $contentDiv.html('<div class="alert alert-danger mb-3">' +
                              '<i class="fe fe-alert-circle me-2"></i><strong>Error:</strong> ' +
                              escapeHtml(data.message || data.error || 'Unknown error') +
                              '</div>' +
                              '<button type="button" class="btn btn-sm btn-secondary" onclick="checkMigrationDetails(\'' + escapeHtml(migrationName) + '\')">' +
                              'Reload Details' +
                              '</button>');
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
            $contentDiv.html('<div class="alert alert-danger mb-3">' +
                          '<i class="fe fe-alert-circle me-2"></i><strong>Error:</strong> ' +
                          escapeHtml(error) +
                          '</div>' +
                          '<button type="button" class="btn btn-sm btn-secondary" onclick="checkMigrationDetails(\'' + escapeHtml(migrationName) + '\')">' +
                          'Reload Details' +
                          '</button>');
        });
    };

    // Check migration details
    window.checkMigrationDetails = function(migrationName) {
        const $modal = $('#migrationDetailsModal');
        const $contentDiv = $('#migrationDetailsContent');
        
        $contentDiv.html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>');
        $modal.modal('show');

        $.ajax({
            url: getUrl('migrationDetails'),
            method: 'GET',
            data: {
                migration: migrationName
            }
        })
        .done(function(data) {
            if (data.success) {
                let html = '<div class="mb-3">';
                html += '<h6 class="mb-3">Migration: <code>' + escapeHtml(data.migration_name) + '</code></h6>';
                html += '<hr class="mb-3">';
                
                // In database status
                html += '<div class="mb-3">';
                html += '<strong>In Database:</strong> ';
                if (data.in_database) {
                    html += '<span class="badge bg-success">Yes</span>';
                    if (data.migration_record) {
                        html += '<div class="mt-2 small">';
                        html += '<strong>Batch:</strong> ' + data.migration_record.batch + '<br>';
                        html += '<strong>Recorded at:</strong> ' + (data.migration_record.created_at || 'N/A');
                        html += '</div>';
                    }
                } else {
                    html += '<span class="badge bg-danger">No</span>';
                }
                html += '</div>';

                // Table exists status
                if (data.table_exists !== null) {
                    html += '<div class="mb-3">';
                    html += '<strong>Table Exists:</strong> ';
                    if (data.table_exists) {
                        html += '<span class="badge bg-warning text-dark">Yes (but migration not recorded!)</span>';
                        html += '<div class="alert alert-warning mt-2 mb-3 small">';
                        html += 'The table exists in the database, but the migration is not recorded. ';
                        html += 'This could mean the migration was run manually or the record was deleted. ';
                        html += 'You can manually record this migration in the database.';
                        html += '</div>';
                        if (!data.in_database) {
                            html += '<div class="d-grid gap-2">';
                            html += '<button type="button" class="btn btn-warning btn-sm" onclick="recordMigration(\'' + escapeHtml(data.migration_name) + '\')">';
                            html += '<i class="fe fe-plus-circle me-1"></i>Record Migration in Database';
                            html += '</button>';
                            html += '</div>';
                        }
                    } else {
                        html += '<span class="badge bg-danger">No</span>';
                        html += '<div class="alert alert-info mt-2 mb-0 small">';
                        html += 'The table does not exist. This migration needs to be run.';
                        html += '</div>';
                    }
                    html += '</div>';
                }

                // Similar records
                if (data.all_similar_records && data.all_similar_records.length > 0) {
                    html += '<div class="mb-3">';
                    html += '<strong>Similar Migration Records:</strong>';
                    html += '<ul class="small mt-2">';
                    $.each(data.all_similar_records, function(i, record) {
                        html += '<li><code>' + escapeHtml(record.migration) + '</code> (Batch: ' + record.batch + ')</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }

                // Recent migrations
                if (data.all_migrations_in_db && data.all_migrations_in_db.length > 0) {
                    html += '<div class="mb-3">';
                    html += '<strong>Recent Migrations in Database (last 50):</strong>';
                    html += '<div class="small mt-2" style="max-height: 200px; overflow-y: auto;">';
                    $.each(data.all_migrations_in_db, function(i, migration) {
                        const isMatch = migration.includes(data.migration_name.split('_').slice(-1)[0]);
                        html += '<div class="' + (isMatch ? 'text-warning fw-bold' : '') + '">';
                        html += escapeHtml(migration);
                        if (isMatch) {
                            html += ' <span class="badge bg-warning text-dark">Similar</span>';
                        }
                        html += '</div>';
                    });
                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>';
                $contentDiv.html(html);
                
                // Show/hide "Run This Migration" button based on migration status
                const $runBtn = $('#runSingleMigrationBtn');
                if ($runBtn.length) {
                    // Show button only if migration is not in database
                    if (!data.in_database) {
                        $runBtn.show().attr('data-migration', data.migration_name);
                        // Store migration path if available
                        if (data.migration_path) {
                            $runBtn.attr('data-migration-path', data.migration_path);
                        }
                    } else {
                        $runBtn.hide().removeAttr('data-migration').removeAttr('data-migration-path');
                    }
                }
            } else {
                $contentDiv.html('<div class="alert alert-danger">' + escapeHtml(data.message || data.error || 'Unknown error') + '</div>');
                // Hide button on error
                $('#runSingleMigrationBtn').hide();
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
            $contentDiv.html('<div class="alert alert-danger">Error loading migration details: ' + escapeHtml(error) + '</div>');
            // Hide button on error
            $('#runSingleMigrationBtn').hide();
        });
    };

    // Run a single specific migration
    window.runSingleMigration = function() {
        const $runBtn = $('#runSingleMigrationBtn');
        if (!$runBtn.length) return;
        
        const migrationName = $runBtn.attr('data-migration');
        const migrationPath = $runBtn.attr('data-migration-path');
        
        if (!migrationName) {
            alert('Migration name not found');
            return;
        }
        
        if (!confirm('Are you sure you want to run this migration?\n\nMigration: ' + migrationName + '\n\nThis will execute the migration and record it in the database.')) {
            return;
        }
        
        // Disable button and show loading
        const originalHtml = $runBtn.html();
        $runBtn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Running...');
        
        // Show output in modal body
        const $contentDiv = $('#migrationDetailsContent');
        const outputHtml = '<div class="alert alert-info mb-3">' +
                          '<i class="fe fe-clock me-2"></i><strong>Running migration...</strong><br>' +
                          '<small>Migration: <code>' + escapeHtml(migrationName) + '</code></small>' +
                          '</div>' +
                          '<div class="command-output p-3 rounded" id="singleMigrationOutput" style="background: #1e1e1e; color: #d4d4d4; font-family: monospace; font-size: 0.875rem; max-height: 300px; overflow-y: auto;">' +
                          '<div class="text-info">Command queued. Check logs for output...</div>' +
                          '</div>';
        $contentDiv.html(outputHtml);
        
        $.ajax({
            url: getUrl('runSingleMigration'),
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            data: JSON.stringify({
                migration: migrationName,
                path: migrationPath
            }),
            contentType: 'application/json'
        })
        .done(function(data) {
            const $outputDiv = $('#singleMigrationOutput');
            if (data.success) {
                if (data.status === 'queued') {
                    $outputDiv.html('<div class="text-success">✓ Migration command queued successfully!</div>' +
                                '<div class="text-muted mt-2">The migration is running in the background.</div>' +
                                '<div class="text-muted small mt-2">Check the logs for output:<br>' +
                                '<code>tail -f storage/logs/laravel.log | grep "RunSingleMigration"</code></div>' +
                                '<div class="text-info mt-3">You can close this modal and check back later, or reload the page to see if the migration was recorded.</div>');
                } else {
                    $outputDiv.html('<div class="text-success">✓ ' + (data.message || 'Migration completed') + '</div>');
                }
                
                // Re-enable button but change text
                $runBtn.prop('disabled', false)
                       .html('<i class="fe fe-check me-1"></i>Migration Queued')
                       .removeClass('btn-primary')
                       .addClass('btn-success');
            } else {
                $outputDiv.html('<div class="text-danger">✗ Error: ' + escapeHtml(data.error || data.message || 'Unknown error') + '</div>');
                $runBtn.prop('disabled', false).html(originalHtml);
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
            $('#singleMigrationOutput').html('<div class="text-danger">✗ Error: ' + escapeHtml(error) + '</div>');
            $runBtn.prop('disabled', false).html(originalHtml);
        });
    };

    // Clear migration output
    window.clearMigrationOutput = function() {
        const $outputDiv = $('#migrationOutput');
        const $outputContainer = $('.migration-output-container');
        $outputDiv.html('').hide();
        $outputContainer.hide();
    };

    // Handle command form submission
    $(document).ready(function() {
        $('.command-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const command = $form.data('command');
            const formData = new FormData(this);
            const options = {};
            
            // Build options object - only include non-empty values
            formData.forEach(function(value, key) {
                if (value !== null && value !== '' && value !== '0') {
                    // Convert string numbers to actual numbers if needed
                    if (!isNaN(value) && value !== '') {
                        options[key] = isNaN(parseFloat(value)) ? value : parseFloat(value);
                    } else {
                        options[key] = value;
                    }
                }
            });
            
            // Handle checkboxes - unchecked checkboxes won't be in FormData, so we need to check them explicitly
            $form.find('input[type="checkbox"]').each(function() {
                const $checkbox = $(this);
                if ($checkbox.is(':checked')) {
                    options[$checkbox.attr('name')] = $checkbox.val() || '1';
                }
            });

            // Show output container
            const $outputContainer = $form.closest('.card-body').find('.command-output-container');
            const $outputDiv = $outputContainer.find('.command-output');
            $outputContainer.show();
            $outputDiv.show().html('<div class="text-info">Executing command...</div>');

            // Execute command
            $.ajax({
                url: getUrl('execute'),
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                data: JSON.stringify({
                    command: command,
                    options: options
                }),
                contentType: 'application/json'
            })
            .done(function(data) {
                if (data.success) {
                    if (data.status === 'queued') {
                        const jobId = data.job_id || null;
                        let message = '<div class="alert alert-info mb-2">' +
                                     '<div class="d-flex justify-content-between align-items-start mb-2">' +
                                     '<div>' +
                                     '<i class="fe fe-clock me-2"></i><strong>Command queued successfully!</strong><br>' +
                                     '<small class="text-muted">Command: <code>' + data.command + '</code></small>';
                        if (jobId) {
                            message += '<br><small class="text-muted">Job ID: <code>' + jobId + '</code></small>';
                        }
                        message += '</div>';
                        
                        // Add Kill and Restart buttons at the top
                        if (jobId) {
                            message += '<div class="d-flex gap-2">' +
                                     '<button type="button" class="btn btn-sm btn-danger" onclick="killCommand(\'' + command + '\', \'' + jobId + '\', this)">' +
                                     '<i class="fe fe-x-circle"></i> Kill' +
                                     '</button>' +
                                     '<button type="button" class="btn btn-sm btn-warning" onclick="restartCommand(\'' + command + '\', ' + JSON.stringify(options).replace(/"/g, '&quot;') + ', \'' + jobId + '\', this)">' +
                                     '<i class="fe fe-refresh-cw"></i> Restart' +
                                     '</button>' +
                                     '</div>';
                        }
                        message += '</div>' +
                                     '<div class="small">' +
                                     'The command is running in the background. ' +
                                     '<strong>Status will be checked automatically...</strong><br><br>' +
                                     '<div id="command-status-check" class="text-muted mb-2">' +
                                     '<i class="fe fe-loader me-1 spin"></i>Checking status...' +
                                     '</div>' +
                                     '</div>' +
                                     '</div>';
                        
                        $outputDiv.html(message);
                        
                        // Store job ID and command info for later use
                        $outputDiv.attr({
                            'data-job-id': jobId || '',
                            'data-command': command,
                            'data-options': JSON.stringify(options)
                        });
                        
                        // Start polling for status updates
                        const marketplaceId = options.marketplace || 1;
                        pollCommandStatus(command, $outputDiv[0], marketplaceId);
                    } else if (data.status === 'completed') {
                        // Synchronous execution completed successfully
                        $outputDiv.html('<div class="alert alert-success mb-2">' +
                                     '<i class="fe fe-check-circle me-2"></i><strong>✓ Command executed successfully!</strong><br>' +
                                     '<small class="text-muted">Command: <code>' + escapeHtml(data.command) + '</code></small>' +
                                     '</div>' +
                                     (data.output ? '<pre class="mb-0 bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">' + escapeHtml(data.output) + '</pre>' : '<div class="text-muted">No output</div>'));
                    } else if (data.status === 'failed') {
                        // Synchronous execution failed
                        $outputDiv.html('<div class="alert alert-danger mb-2">' +
                                     '<i class="fe fe-x-circle me-2"></i><strong>✗ Command failed</strong><br>' +
                                     '<small class="text-muted">Command: <code>' + escapeHtml(data.command) + '</code></small>' +
                                     '</div>' +
                                     (data.output ? '<pre class="mb-0 bg-light p-3 rounded text-danger" style="max-height: 400px; overflow-y: auto;">' + escapeHtml(data.output) + '</pre>' : '<div class="text-danger">' + escapeHtml(data.message || 'Unknown error') + '</div>'));
                    } else {
                        // Fallback for other statuses
                        $outputDiv.html('<div class="text-success mb-2">✓ ' + (data.message || 'Command executed successfully') + '</div>' +
                                     '<div class="text-muted small mb-2">Command: <code>' + escapeHtml(data.command) + '</code></div>' +
                                     (data.output ? '<pre class="mb-0">' + escapeHtml(data.output) + '</pre>' : ''));
                    }
                } else {
                    // Command failed - show error and output if available
                    let errorHtml = '<div class="alert alert-danger mb-2">' +
                                   '<i class="fe fe-x-circle me-2"></i><strong>✗ Command failed</strong><br>' +
                                   '<small class="text-muted">Command: <code>' + escapeHtml(data.command || command) + '</code></small>' +
                                   '</div>';
                    
                    if (data.output) {
                        errorHtml += '<pre class="mb-0 bg-light p-3 rounded text-danger" style="max-height: 400px; overflow-y: auto;">' + escapeHtml(data.output) + '</pre>';
                    } else {
                        errorHtml += '<pre class="mb-0 text-danger">' + escapeHtml(data.error || data.message || 'Unknown error') + '</pre>';
                    }
                    
                    $outputDiv.html(errorHtml);
                }
            })
            .fail(function(xhr) {
                const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
                $outputDiv.html('<div class="text-danger">Error: ' + escapeHtml(error) + '</div>');
            });
        });
    });

    // Poll command status automatically (for queued commands)
    window.pollCommandStatus = function(command, outputDiv, marketplaceId = 1, pollCount = 0) {
        const maxPolls = 120; // Poll for up to 10 minutes (120 * 5 seconds)
        const $outputDiv = $(outputDiv);
        
        if (pollCount >= maxPolls) {
            const $statusDiv = $('#command-status-check');
            if ($statusDiv.length) {
                $statusDiv.html('<div class="text-warning">Status check timeout. Please check logs manually or refresh the page.</div>');
            }
            return;
        }
        
        let url = getUrl('checkCommandStatus') + '?command=' + encodeURIComponent(command);
        if (command === 'v2:sync-all-marketplace-stock-from-api') {
            url += '&marketplace=' + marketplaceId;
        }
        
        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken()
            }
        })
        .done(function(data) {
            const $statusDiv = $('#command-status-check');
            
            if (data.success) {
                if (data.status === 'completed') {
                    // Command completed - show success message
                    let successHtml = '<div class="alert alert-success mb-0">' +
                                    '<i class="fe fe-check-circle me-2"></i><strong>✓ Command Completed!</strong><br>';
                    
                    if (command === 'v2:sync-all-marketplace-stock-from-api' && data.summary) {
                        successHtml += '<div class="mt-2 small">' +
                                     '<strong>Summary:</strong> ' + escapeHtml(data.summary) + '<br>';
                        if (data.total_records !== null) {
                            successHtml += 'Total: ' + data.total_records + ' | ';
                            successHtml += 'Synced: ' + data.synced_count + ' | ';
                            successHtml += 'Skipped: ' + data.skipped_count + ' | ';
                            successHtml += 'Errors: ' + data.error_count + '<br>';
                        }
                        if (data.duration_seconds !== null) {
                            successHtml += 'Duration: ' + data.duration_seconds + ' seconds<br>';
                        }
                        if (data.log_id) {
                            successHtml += '<a href="' + getUrl('stockSyncLog') + '/' + data.log_id + '" class="btn btn-sm btn-primary mt-2">View Full Log Details</a>';
                        }
                        successHtml += '</div>';
                    } else {
                        if (data.completed_at) {
                            successHtml += '<small>Completed at: ' + escapeHtml(data.completed_at) + '</small>';
                        }
                    }
                    
                    successHtml += '</div>';
                    
                    if ($statusDiv.length) {
                        $statusDiv.replaceWith(successHtml);
                    } else {
                        $outputDiv.html(successHtml);
                    }
                    
                    // Stop polling
                    return;
                } else if (data.status === 'failed') {
                    // Command failed
                    let errorHtml = '<div class="alert alert-danger mb-0">' +
                                  '<i class="fe fe-x-circle me-2"></i><strong>✗ Command Failed</strong><br>';
                    if (data.completed_at) {
                        errorHtml += '<small>Failed at: ' + escapeHtml(data.completed_at) + '</small>';
                    }
                    errorHtml += '</div>';
                    
                    if ($statusDiv.length) {
                        $statusDiv.replaceWith(errorHtml);
                    } else {
                        $outputDiv.html(errorHtml);
                    }
                    
                    // Stop polling
                    return;
                } else if (data.status === 'running') {
                    // Still running - update status and continue polling
                    if ($statusDiv.length) {
                        let runningText = '<i class="fe fe-loader me-1 spin"></i>Running...';
                        if (command === 'v2:sync-all-marketplace-stock-from-api' && data.synced_count !== null) {
                            runningText += ' (Synced: ' + data.synced_count + '/' + (data.total_records || '?') + ')';
                        }
                        $statusDiv.html(runningText);
                    }
                } else if (data.status === 'queued') {
                    // Still queued
                    if ($statusDiv.length) {
                        $statusDiv.html('<i class="fe fe-clock me-1"></i>Queued...');
                    }
                }
            }
            
            // Continue polling if not completed or failed
            if (data.status !== 'completed' && data.status !== 'failed') {
                setTimeout(function() {
                    pollCommandStatus(command, outputDiv, marketplaceId, pollCount + 1);
                }, 5000); // Poll every 5 seconds
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
            const $statusDiv = $('#command-status-check');
            if ($statusDiv.length) {
                $statusDiv.html('<div class="text-danger">Error checking status: ' + escapeHtml(error) + '</div>');
            }
            
            // Continue polling even on error (might be temporary)
            if (pollCount < maxPolls) {
                setTimeout(function() {
                    pollCommandStatus(command, outputDiv, marketplaceId, pollCount + 1);
                }, 5000);
            }
        });
    };

    // Check command status by looking at recent logs
    window.checkCommandStatus = function(command, buttonElement) {
        if (!buttonElement) return;
        
        const $btn = $(buttonElement);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Checking...');
        
        // Get marketplace ID from form if it's the sync command
        let url = getUrl('checkCommandStatus') + '?command=' + encodeURIComponent(command);
        if (command === 'v2:sync-all-marketplace-stock-from-api') {
            const $commandForm = $('[data-command="' + escapeHtml(command) + '"]');
            if ($commandForm.length) {
                const marketplaceId = $commandForm.find('input[name="options[marketplace]"]').val() || 1;
                url += '&marketplace=' + marketplaceId;
            }
        }
        
        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken()
            }
        })
        .done(function(data) {
            $btn.prop('disabled', false).html(originalHtml);
            
            if (data.success) {
                // Find the output div for this command form
                const $commandForm = $('[data-command="' + escapeHtml(command) + '"]');
                const $outputDiv = $commandForm.length ? $commandForm.closest('.card-body').find('.command-output') : null;
                
                if ($outputDiv && $outputDiv.length) {
                    let statusHtml = '<div class="alert alert-info mb-2 mt-2">' +
                                   '<strong>Command Status Check</strong><br>' +
                                   '<small>Command: <code>' + escapeHtml(command) + '</code></small><br><br>';
                    
                    if (data.status === 'completed') {
                        statusHtml += '<div class="alert alert-success">' +
                                     '<i class="fe fe-check-circle me-2"></i><strong>✓ Command Completed!</strong><br>' +
                                     '<small>Completed at: ' + (data.completed_at || 'Recently') + '</small><br>';
                        if (data.exit_code !== null) {
                            statusHtml += '<small>Exit Code: ' + data.exit_code + '</small><br>';
                        }
                        // Show sync log details if available
                        if (command === 'v2:sync-all-marketplace-stock-from-api' && data.summary) {
                            statusHtml += '<div class="mt-2 small">' +
                                         '<strong>Summary:</strong> ' + escapeHtml(data.summary) + '<br>';
                            if (data.total_records !== null) {
                                statusHtml += 'Total: ' + data.total_records + ' | ';
                                statusHtml += 'Synced: ' + data.synced_count + ' | ';
                                statusHtml += 'Skipped: ' + data.skipped_count + ' | ';
                                statusHtml += 'Errors: ' + data.error_count + '<br>';
                            }
                            if (data.duration_seconds !== null) {
                                statusHtml += 'Duration: ' + data.duration_seconds + ' seconds<br>';
                            }
                            if (data.log_id) {
                                statusHtml += '<a href="' + getUrl('stockSyncLog') + '/' + data.log_id + '" class="btn btn-sm btn-primary mt-2">View Full Log Details</a>';
                            }
                            statusHtml += '</div>';
                        }
                        statusHtml += '</div>';
                    } else if (data.status === 'running') {
                        statusHtml += '<div class="alert alert-warning">' +
                                     '<i class="fe fe-loader me-2"></i><strong>Command Still Running...</strong><br>' +
                                     '<small>Started at: ' + (data.started_at || 'Recently') + '</small><br>' +
                                     '<small>Please check again in a moment.</small>' +
                                     '</div>';
                    } else if (data.status === 'queued') {
                        statusHtml += '<div class="alert alert-secondary">' +
                                     '<i class="fe fe-clock me-2"></i><strong>Command Queued</strong><br>' +
                                     '<small>The command has been queued and is waiting to run.</small>' +
                                     '</div>';
                    } else {
                        statusHtml += '<div class="alert alert-secondary">' +
                                     '<i class="fe fe-info me-2"></i><strong>Status Unknown</strong><br>' +
                                     '<small>No recent execution found in logs. The command may not have started yet, or logs may have been cleared.</small>' +
                                     '</div>';
                    }
                    
                    if (data.last_log_entry) {
                        statusHtml += '<div class="mt-2 small">' +
                                     '<strong>Last Log Entry:</strong><br>' +
                                     '<code class="small" style="word-break: break-all;">' + escapeHtml(data.last_log_entry.substring(0, 200)) + (data.last_log_entry.length > 200 ? '...' : '') + '</code>' +
                                     '</div>';
                    }
                    
                    statusHtml += '</div>';
                    $outputDiv.prepend(statusHtml);
                }
            } else {
                alert('Error checking status: ' + (data.error || 'Unknown error'));
            }
        })
        .fail(function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
            alert('Error checking status: ' + error);
        });
    };

    // Show documentation
    window.showDocumentation = function(filename) {
        const $modal = $('#documentationModal');
        const $contentDiv = $('#documentationContent');
        
        $contentDiv.html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>');
        $modal.modal('show');

        $.ajax({
            url: getUrl('documentation'),
            method: 'GET',
            data: {
                file: filename
            }
        })
        .done(function(data) {
            if (data.success) {
                // Convert markdown to HTML
                let html = '<div class="mb-3">';
                html += '<h4 class="mb-3">' + data.filename.replace('.md', '').replace(/_/g, ' ') + '</h4>';
                html += '<hr class="mb-4">';
                html += convertMarkdownToHtml(data.content);
                html += '</div>';
                $contentDiv.html(html);
            } else {
                $contentDiv.html('<div class="alert alert-danger">' + data.message + '</div>');
            }
        })
        .fail(function(xhr) {
            const error = xhr.responseJSON?.error || xhr.statusText || 'Unknown error';
            $contentDiv.html('<div class="alert alert-danger">Error loading documentation: ' + error + '</div>');
        });
    };

    // Clear output
    window.clearOutput = function(btn) {
        const $btn = $(btn);
        const $outputDiv = $btn.closest('.command-output-container').find('.command-output');
        $outputDiv.html('').hide();
        $btn.closest('.command-output-container').hide();
    };

    // Simple markdown to HTML converter
    function convertMarkdownToHtml(markdown) {
        let html = markdown;
        
        // Code blocks (do first to avoid conflicts)
        html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre class="bg-dark text-light p-3 rounded"><code>$2</code></pre>');
        html = html.replace(/```([\s\S]*?)```/g, '<pre class="bg-dark text-light p-3 rounded"><code>$1</code></pre>');
        
        // Inline code
        html = html.replace(/`([^`\n]+)`/g, '<code class="bg-light px-1 rounded">$1</code>');
        
        // Headers
        html = html.replace(/^#### (.*$)/gim, '<h4 class="mt-4 mb-2">$1</h4>');
        html = html.replace(/^### (.*$)/gim, '<h3 class="mt-4 mb-2">$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2 class="mt-4 mb-3">$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1 class="mt-4 mb-3">$1</h1>');
        
        // Bold and italic
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Unordered lists
        html = html.replace(/^[\*\-] (.*$)/gim, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/s, '<ul class="mb-3">$1</ul>');
        
        // Ordered lists
        html = html.replace(/^\d+\. (.*$)/gim, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/s, '<ol class="mb-3">$1</ol>');
        
        // Links
        html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // Horizontal rules
        html = html.replace(/^---$/gim, '<hr>');
        html = html.replace(/^\*\*\*$/gim, '<hr>');
        
        // Tables (basic)
        html = html.replace(/\|(.+)\|/g, function(match, content) {
            const cells = content.split('|').map(function(c) { return c.trim(); }).filter(function(c) { return c; });
            return '<tr>' + cells.map(function(c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
        });
        
        // Line breaks
        html = html.replace(/\n\n/g, '</p><p class="mb-2">');
        html = '<div class="markdown-body"><p class="mb-2">' + html + '</p></div>';
        
        return html;
    }

    // Kill a running command
    window.killCommand = function(command, jobId, buttonElement) {
        if (!confirm('Are you sure you want to kill this command? This will stop the running job.')) {
            return;
        }
        
        const $btn = $(buttonElement);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Killing...');
        
        $.ajax({
            url: getUrl('kill'),
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            data: JSON.stringify({
                command: command,
                job_id: jobId
            }),
            contentType: 'application/json'
        })
        .done(function(data) {
            if (data.success) {
                showAlert('success', data.message || 'Command killed successfully');
                
                // Update the status check area
                const $statusDiv = $('#command-status-check');
                if ($statusDiv.length) {
                    $statusDiv.html('<div class="text-danger"><i class="fe fe-x-circle me-1"></i>Command killed</div>');
                }
                
                // Hide kill/restart buttons
                $btn.closest('.mt-2').hide();
            } else {
                showAlert('danger', data.error || 'Failed to kill command');
                $btn.prop('disabled', false).html(originalHtml);
            }
        })
        .fail(function(xhr) {
            console.error('Error:', xhr);
            showAlert('danger', 'An error occurred while killing the command');
            $btn.prop('disabled', false).html(originalHtml);
        });
    };

    // Restart a command
    window.restartCommand = function(command, options, oldJobId, buttonElement) {
        if (!confirm('Are you sure you want to restart this command? This will kill the current job and start a new one.')) {
            return;
        }
        
        const $btn = $(buttonElement);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Restarting...');
        
        $.ajax({
            url: getUrl('restart'),
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            data: JSON.stringify({
                command: command,
                options: options,
                job_id: oldJobId
            }),
            contentType: 'application/json'
        })
        .done(function(data) {
            if (data.success) {
                showAlert('success', data.message || 'Command restarted successfully');
                
                // Update the output div with new job info
                const $outputDiv = $btn.closest('.command-output-container').find('.command-output');
                if ($outputDiv.length && data.job_id) {
                    $outputDiv.attr('data-job-id', data.job_id);
                    
                    // Update job ID in the message if it exists
                    $outputDiv.find('code').each(function() {
                        const $el = $(this);
                        if ($el.text().includes('Job ID') || ($el.prev().length && $el.prev().text().includes('Job ID'))) {
                            const $container = $el.closest('small');
                            if ($container.length) {
                                $container.html('Job ID: <code>' + data.job_id + '</code>');
                            }
                        }
                    });
                    
                    // Update kill/restart buttons with new job ID
                    const $killBtn = $outputDiv.find('button[onclick*="killCommand"]');
                    const $restartBtn = $outputDiv.find('button[onclick*="restartCommand"]');
                    if ($killBtn.length) {
                        $killBtn.attr('onclick', 'killCommand(\'' + command + '\', \'' + data.job_id + '\', this)');
                    }
                    if ($restartBtn.length) {
                        $restartBtn.attr('onclick', 'restartCommand(\'' + command + '\', ' + JSON.stringify(options).replace(/"/g, '&quot;') + ', \'' + data.job_id + '\', this)');
                    }
                }
                
                // Reset button
                $btn.prop('disabled', false).html(originalHtml);
                
                // Restart polling
                const marketplaceId = options.marketplace || 1;
                pollCommandStatus(command, $outputDiv[0], marketplaceId);
            } else {
                showAlert('danger', data.error || 'Failed to restart command');
                $btn.prop('disabled', false).html(originalHtml);
            }
        })
        .fail(function(xhr) {
            console.error('Error:', xhr);
            showAlert('danger', 'An error occurred while restarting the command');
            $btn.prop('disabled', false).html(originalHtml);
        });
    };

    // Kill command from the running jobs list
    window.killCommandFromList = function(command, jobId, buttonElement) {
        if (!confirm('Are you sure you want to kill this command? This will stop the running job.')) {
            return;
        }
        
        const $btn = $(buttonElement);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Killing...');
        
        $.ajax({
            url: getUrl('kill'),
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            data: JSON.stringify({
                command: command,
                job_id: jobId
            }),
            contentType: 'application/json'
        })
        .done(function(data) {
            if (data.success) {
                showAlert('success', data.message || 'Command killed successfully');
                // Refresh the running jobs list
                refreshRunningJobs();
            } else {
                showAlert('danger', data.error || 'Failed to kill command');
                $btn.prop('disabled', false).html(originalHtml);
            }
        })
        .fail(function(xhr) {
            console.error('Error:', xhr);
            showAlert('danger', 'An error occurred while killing the command');
            $btn.prop('disabled', false).html(originalHtml);
        });
    };

    // Restart command from the running jobs list
    window.restartCommandFromList = function(command, options, oldJobId, buttonElement) {
        if (!confirm('Are you sure you want to restart this command? This will kill the current job and start a new one.')) {
            return;
        }
        
        const $btn = $(buttonElement);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fe fe-loader me-1 spin"></i>Restarting...');
        
        // Convert options object to proper format
        const optionsObj = typeof options === 'string' ? JSON.parse(options.replace(/&quot;/g, '"')) : options;
        
        $.ajax({
            url: getUrl('restart'),
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            data: JSON.stringify({
                command: command,
                options: optionsObj,
                job_id: oldJobId
            }),
            contentType: 'application/json'
        })
        .done(function(data) {
            if (data.success) {
                showAlert('success', data.message || 'Command restarted successfully');
                // Refresh the running jobs list
                refreshRunningJobs();
            } else {
                showAlert('danger', data.error || 'Failed to restart command');
                $btn.prop('disabled', false).html(originalHtml);
            }
        })
        .fail(function(xhr) {
            console.error('Error:', xhr);
            showAlert('danger', 'An error occurred while restarting the command');
            $btn.prop('disabled', false).html(originalHtml);
        });
    };

    // Refresh running jobs list
    window.refreshRunningJobs = function() {
        const $container = $('#runningJobsContainer');
        if (!$container.length) return;
        
        $container.html('<div class="text-center p-3"><i class="fe fe-loader spin"></i> Refreshing...</div>');
        
        // Reload the page to get updated running jobs
        setTimeout(function() {
            window.location.reload();
        }, 500);
    };

    // Show alert
    window.showAlert = function(type, message) {
        // Create alert element
        const $alertDiv = $('<div>')
            .addClass('alert alert-' + type + ' alert-dismissible fade show position-fixed')
            .css({
                'top': '20px',
                'right': '20px',
                'z-index': '9999',
                'min-width': '300px'
            })
            .html(message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
        
        // Add to page
        $('body').append($alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $alertDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    };

    // Load PM2 logs
    window.loadPm2Logs = function() {
        const $container = $('#pm2LogsContainer');
        const $refreshIcon = $('#pm2LogsRefreshIcon');
        const lines = $('#pm2LogsLines').val() || 100;
        
        if (!$container.length) return;
        
        // Show loading state
        $container.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading PM2 logs...</p></div>');
        $refreshIcon.addClass('spin');
        
        $.ajax({
            url: getUrl('pm2Logs'),
            method: 'GET',
            data: { lines: lines },
            headers: {
                'X-CSRF-TOKEN': getCsrfToken()
            }
        })
        .done(function(data) {
            $refreshIcon.removeClass('spin');
            
            if (data.success) {
                if (data.logs) {
                    // Format logs with line breaks and color coding
                    const logLines = data.logs.split('\n');
                    let html = '';
                    
                    logLines.forEach(function(line) {
                        if (!line.trim()) {
                            html += '<div class="log-line"><br></div>';
                            return;
                        }
                        
                        let lineClass = '';
                        const lowerLine = line.toLowerCase();
                        
                        // Detect error patterns
                        if (lowerLine.includes('error') || lowerLine.includes('exception') || lowerLine.includes('failed') || lowerLine.includes('fatal')) {
                            lineClass = 'error';
                        } else if (lowerLine.includes('warning') || lowerLine.includes('warn')) {
                            lineClass = 'warning';
                        } else if (lowerLine.includes('info') || lowerLine.includes('information')) {
                            lineClass = 'info';
                        } else if (lowerLine.includes('debug')) {
                            lineClass = 'debug';
                        }
                        
                        html += '<div class="log-line ' + lineClass + '">' + escapeHtml(line) + '</div>';
                    });
                    
                    $container.html(html);
                    
                    // Auto-scroll to bottom to show most recent logs
                    $container.scrollTop($container[0].scrollHeight);
                } else {
                    $container.html('<div class="alert alert-info m-3">No PM2 logs found. Make sure PM2 processes are running.</div>');
                }
            } else {
                $container.html('<div class="alert alert-danger m-3">' + escapeHtml(data.error || 'Error loading PM2 logs') + '</div>');
            }
        })
        .fail(function(xhr) {
            $refreshIcon.removeClass('spin');
            let errorMsg = 'Error loading PM2 logs';
            
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            } else if (xhr.statusText) {
                errorMsg += ': ' + xhr.statusText;
            }
            
            $container.html('<div class="alert alert-danger m-3">' + escapeHtml(errorMsg) + '</div>');
        });
    };

    // Load PM2 logs on page load if container exists
    $(document).ready(function() {
        if ($('#pm2LogsContainer').length) {
            loadPm2Logs();
            
            // Reload logs when line count changes
            $('#pm2LogsLines').on('change', function() {
                loadPm2Logs();
            });
            
            // Auto-refresh PM2 logs every 30 seconds
            setInterval(function() {
                loadPm2Logs();
            }, 30000);
        }
    });

})(jQuery);
