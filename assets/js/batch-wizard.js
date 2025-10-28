(function( $ ) {
        'use strict';

        $( function() {
                const data = window.ele2gbWizardData || {};
                const strings = data.strings || {};
                const steps = $( '.ele2gb-step' );
                const nav = $( '.ele2gb-step-bubble' );
                const selectionNote = $( '#ele2gb-selection-note' );
                const startButton = $( '#ele2gb-start-run' );
                const cancelButton = $( '#ele2gb-cancel-run' );
                const exportButton = $( '#ele2gb-export-results' );
                const progressFill = $( '.ele2gb-progress-fill' );
                const progressLabel = $( '#ele2gb-progress-label' );
                const resultsList = $( '#ele2gb-results' );
                const messages = $( '#ele2gb-messages' );
                const summaryList = $( '#ele2gb-summary-list' );
                const storageKey = 'ele2gb-selected-' + ( data.userId || '0' );
                const defaultStartLabel = startButton.text();

                let selectedIds = new Set();
                let selectAllMatching = false;
                let optionsState = {};
                let queueState = null;
                let isProcessing = false;

                function safeParseSelection( value ) {
                        try {
                                const parsed = JSON.parse( value );
                                if ( Array.isArray( parsed ) ) {
                                        return parsed.map( function( id ) {
                                                return parseInt( id, 10 );
                                        } ).filter( function( id ) {
                                                return ! Number.isNaN( id );
                                        } );
                                }
                        } catch ( e ) {
                                // Ignore parse issues.
                        }
                        return [];
                }

                function loadSelection() {
                        if ( ! window.localStorage ) {
                                selectedIds = new Set();
                                return;
                        }
                        const stored = window.localStorage.getItem( storageKey );
                        if ( stored ) {
                                selectedIds = new Set( safeParseSelection( stored ) );
                        }
                }

                function storeSelection() {
                        if ( ! window.localStorage ) {
                                return;
                        }
                        window.localStorage.setItem( storageKey, JSON.stringify( Array.from( selectedIds ) ) );
                }

                function syncCheckboxes() {
                        $( '.ele2gb-page-checkbox' ).each( function() {
                                const pageId = parseInt( $( this ).data( 'pageId' ), 10 );
                                if ( selectedIds.has( pageId ) ) {
                                        $( this ).prop( 'checked', true );
                                }
                        } );
                }

                function updateSelectionNote() {
                        const count = selectedIds.size;
                        let text = '';
                        if ( selectAllMatching ) {
                                text = strings.selectAllLabel || '';
                        } else if ( count > 0 ) {
                                const template = 1 === count ? strings.selectedSingle : strings.selectedPlural;
                                if ( template ) {
                                        text = template.replace( '%s', count );
                                } else {
                                        text = count + ( 1 === count ? ' page selected' : ' pages selected' );
                                }
                        } else {
                                text = strings.noSelection || '';
                        }

                        selectionNote.text( text );
                        $( '#ele2gb-step1-next' ).prop( 'disabled', ! selectAllMatching && count === 0 );
                }

                function goToStep( step ) {
                        steps.attr( 'hidden', true );
                        steps.filter( '[data-step="' + step + '"]' ).attr( 'hidden', false );
                        nav.removeClass( 'is-active' );
                        nav.filter( '[data-step="' + step + '"]' ).addClass( 'is-active' );
                }

                function collectOptions() {
                        const formData = $( '#ele2gb-options-form' ).serializeArray();
                        const result = {
                                mode: 'update',
                                assign_template: false,
                                wrap_full_width: false,
                                preserve_original: false,
                                keep_meta: false,
                                skip_converted: false,
                        };

                        formData.forEach( function( entry ) {
                                if ( 'mode' === entry.name ) {
                                        result.mode = 'create' === entry.value ? 'create' : 'update';
                                } else {
                                        result[ entry.name ] = true;
                                }
                        } );

                        if ( ! result.skip_converted ) {
                                result.skip_converted = $( '#ele2gb-options-form input[name="skip_converted"]' ).is( ':checked' );
                        }

                        return result;
                }

                function renderSummary() {
                        summaryList.empty();

                        let total;
                        if ( queueState && queueState.total ) {
                                total = queueState.total;
                        } else {
                                total = selectAllMatching ? ( data.totalMatching || 0 ) : selectedIds.size;
                        }
                        const totalTemplate = 1 === total ? strings.selectedSingle : strings.selectedPlural;
                        const totalText = totalTemplate ? totalTemplate.replace( '%s', total ) : total + ' pages selected';
                        summaryList.append( $( '<li />' ).text( totalText ) );

                        if ( selectAllMatching && strings.summaryAllMatching ) {
                                summaryList.append( $( '<li />' ).text( strings.summaryAllMatching ) );
                        }

                        const mode = optionsState.mode === 'create' ? 'create' : 'update';
                        summaryList.append( $( '<li />' ).text( 'create' === mode ? strings.modeCreate : strings.modeUpdate ) );

                        const optionLabels = [];
                        if ( optionsState.assign_template ) {
                                optionLabels.push( strings.optionAssignTemplate );
                        }
                        if ( optionsState.wrap_full_width ) {
                                optionLabels.push( strings.optionWrap );
                        }
                        if ( optionsState.preserve_original && 'update' === mode ) {
                                optionLabels.push( strings.optionPreserve );
                        }
                        if ( optionsState.keep_meta ) {
                                optionLabels.push( strings.optionKeepMeta );
                        }
                        const skipConverted = optionsState.skip_converted !== undefined ? optionsState.skip_converted : true;
                        const skipLabel = skipConverted ? strings.optionSkipConverted : strings.optionSkipConvertedOff;
                        if ( skipLabel ) {
                                optionLabels.push( skipLabel );
                        }

                        if ( optionLabels.length ) {
                                optionLabels.forEach( function( label ) {
                                        if ( label ) {
                                                summaryList.append( $( '<li />' ).text( label ) );
                                        }
                                } );
                        } else {
                                summaryList.append( $( '<li />' ).text( strings.optionNone || '' ) );
                        }

                        startButton.text( strings.startButton || defaultStartLabel );
                }

                function resetProgressUI() {
                        messages.empty();
                        resultsList.empty();
                        progressFill.css( 'width', '0%' );
                        progressLabel.text( '' );
                        exportButton.prop( 'disabled', true );
                        cancelButton.prop( 'disabled', true );
                }

                function updateProgress() {
                        if ( ! queueState ) {
                                return;
                        }

                        const processed = queueState.results ? queueState.results.length : 0;
                        const total = queueState.total || processed + ( queueState.pending ? queueState.pending.length : 0 );
                        const percent = total ? Math.round( ( processed / total ) * 100 ) : 0;
                        progressFill.css( 'width', percent + '%' );

                        if ( isProcessing ) {
                                const label = strings.processing ? strings.processing.replace( '%1$d', processed ).replace( '%2$d', total ) : '';
                                progressLabel.text( label );
                        }

                        renderResults();
                }

                function renderResults() {
                        resultsList.empty();
                        if ( ! queueState || ! Array.isArray( queueState.results ) ) {
                                return;
                        }

                        queueState.results.forEach( function( item ) {
                                const li = $( '<li />' ).addClass( 'status-' + item.status );
                                const title = item.title ? item.title : '#' + item.post_id;
                                const statusLabel = strings[ item.status ] || item.status;
                                const message = item.message || '';
                                const text = title + ' — ' + statusLabel + ( message ? ': ' + message : '' );
                                li.text( text );
                                resultsList.append( li );
                        } );
                }

                function showMessage( text, type ) {
                        if ( ! text ) {
                                messages.text( '' ).removeClass( 'is-error is-success is-info' );
                                return;
                        }
                        messages.text( text ).removeClass( 'is-error is-success is-info' ).addClass( 'is-' + ( type || 'info' ) );
                }

                function disableWizardInputs( disabled ) {
                        $( '#ele2gb-step1-next, #ele2gb-select-all-matching, #ele2gb-options-form input, #ele2gb-options-form button' ).prop( 'disabled', disabled );
                }

                function startQueue() {
                        if ( isProcessing ) {
                                return;
                        }

                        if ( ! selectAllMatching && selectedIds.size === 0 ) {
                                window.alert( strings.noSelection || '' );
                                return;
                        }

                        disableWizardInputs( true );
                        resetProgressUI();
                        isProcessing = true;
                        cancelButton.prop( 'disabled', false );
                        showMessage( '', 'info' );

                        $.post( data.ajaxUrl, {
                                action: 'ele2gb_start_batch',
                                nonce: data.nonce,
                                selected_ids: Array.from( selectedIds ),
                                select_all_matching: selectAllMatching ? 1 : 0,
                                options: optionsState,
                                filters: data.filters || {},
                        } )
                                .done( function( response ) {
                                        if ( ! response || ! response.success ) {
                                                const message = response && response.data && response.data.message ? response.data.message : strings.startError;
                                                showMessage( message, 'error' );
                                                isProcessing = false;
                                                disableWizardInputs( false );
                                                return;
                                        }

                                        queueState = response.data.queue || null;
                                        updateProgress();
                                        processNext();
                                } )
                                .fail( function() {
                                        showMessage( strings.ajaxError, 'error' );
                                        isProcessing = false;
                                        disableWizardInputs( false );
                                } );
                }

                function processNext() {
                        if ( ! queueState || ! queueState.id ) {
                                showMessage( strings.startError, 'error' );
                                isProcessing = false;
                                disableWizardInputs( false );
                                return;
                        }

                        if ( queueState.pending && queueState.pending.length === 0 ) {
                                finalizeRun();
                                return;
                        }

                        updateProgress();

                        $.post( data.ajaxUrl, {
                                action: 'ele2gb_next_batch_item',
                                nonce: data.nonce,
                                queue_id: queueState.id,
                        } )
                                .done( function( response ) {
                                        if ( ! response || ! response.success ) {
                                                showMessage( ( response && response.data && response.data.message ) || strings.ajaxError, 'error' );
                                                isProcessing = false;
                                                disableWizardInputs( false );
                                                return;
                                        }

                                        queueState = response.data.queue || queueState;
                                        updateProgress();

                                        if ( response.data.complete ) {
                                                finalizeRun();
                                        } else {
                                                window.setTimeout( processNext, 200 );
                                        }
                                } )
                                .fail( function() {
                                        showMessage( strings.ajaxError, 'error' );
                                        isProcessing = false;
                                        disableWizardInputs( false );
                                } );
                }

                function finalizeRun( cancelled ) {
                        isProcessing = false;
                        disableWizardInputs( false );
                        cancelButton.prop( 'disabled', true );
                        exportButton.prop( 'disabled', false );
                        startButton.text( strings.startButton || defaultStartLabel );

                        if ( queueState ) {
                                const processed = queueState.results ? queueState.results.length : 0;
                                const skipped = queueState.results ? queueState.results.filter( function( item ) {
                                        return 'skipped' === item.status;
                                } ).length : 0;
                                const failed = queueState.results ? queueState.results.filter( function( item ) {
                                        return 'failed' === item.status;
                                } ).length : 0;
                                const success = processed - skipped - failed;

                                if ( cancelled ) {
                                        const text = strings.cancelled ? strings.cancelled.replace( '%1$d', processed ) : '';
                                        showMessage( text, 'info' );
                                } else {
                                        const text = strings.complete ? strings.complete
                                                .replace( '%1$d', success )
                                                .replace( '%2$d', skipped )
                                                .replace( '%3$d', failed ) : '';
                                        showMessage( text, 'success' );
                                }
                        }
                }

                function cancelQueue() {
                        if ( ! queueState || ! queueState.id ) {
                                return;
                        }

                        cancelButton.prop( 'disabled', true );

                        $.post( data.ajaxUrl, {
                                action: 'ele2gb_cancel_batch',
                                nonce: data.nonce,
                                queue_id: queueState.id,
                        } )
                                .done( function( response ) {
                                        if ( response && response.success ) {
                                                if ( queueState ) {
                                                        queueState.pending = [];
                                                        queueState.completed = true;
                                                }
                                                finalizeRun( true );
                                        } else {
                                                showMessage( ( response && response.data && response.data.message ) || strings.ajaxError, 'error' );
                                        }
                                } )
                                .fail( function() {
                                        showMessage( strings.ajaxError, 'error' );
                                } );
                }

                function exportResults() {
                        if ( ! queueState || ! Array.isArray( queueState.results ) || ! queueState.results.length ) {
                                return;
                        }

                        const rows = [ [ 'Source ID', 'Title', 'Status', 'Message', 'Target ID' ] ];
                        queueState.results.forEach( function( item ) {
                                rows.push( [ item.post_id, item.title || '', item.status, item.message || '', item.target_id || '' ] );
                        } );

                        const csv = rows.map( function( row ) {
                                return row.map( function( value ) {
                                        const str = String( value ).replace( /"/g, '""' );
                                        return '"' + str + '"';
                                } ).join( ',' );
                        } ).join( '\n' );

                        const blob = new window.Blob( [ csv ], { type: 'text/csv;charset=utf-8;' } );
                        const url = window.URL.createObjectURL( blob );
                        const link = document.createElement( 'a' );
                        link.href = url;
                        link.download = strings.exportFileName || 'ele2gb-results.csv';
                        document.body.appendChild( link );
                        link.click();
                        document.body.removeChild( link );
                        window.URL.revokeObjectURL( url );
                }

                function resumeExistingQueue() {
                        if ( ! data.queue || ! data.queue.id || ( data.queue.pending && ! data.queue.pending.length && data.queue.completed ) ) {
                                return;
                        }

                        queueState = data.queue;
                        selectAllMatching = !! queueState.select_all;
                        optionsState = queueState.options || {};

                        showMessage( strings.resumePrompt || '', 'info' );
                        renderSummary();
                        renderResults();
                        updateProgress();

                        startButton.text( strings.resumeButton || defaultStartLabel );
                        goToStep( 3 );
                }

                function attachEvents() {
                        $( document ).on( 'change', '.ele2gb-page-checkbox', function() {
                                const id = parseInt( $( this ).data( 'pageId' ), 10 );
                                if ( Number.isNaN( id ) ) {
                                        return;
                                }
                                if ( $( this ).is( ':checked' ) ) {
                                        selectedIds.add( id );
                                } else {
                                        selectedIds.delete( id );
                                        selectAllMatching = false;
                                }
                                storeSelection();
                                updateSelectionNote();
                        } );

                        $( '#cb-select-all-1, #cb-select-all-2' ).on( 'change', function() {
                                const checked = $( this ).is( ':checked' );
                                $( '.ele2gb-page-checkbox' ).each( function() {
                                        const id = parseInt( $( this ).data( 'pageId' ), 10 );
                                        if ( Number.isNaN( id ) ) {
                                                return;
                                        }
                                        if ( checked ) {
                                                selectedIds.add( id );
                                                $( this ).prop( 'checked', true );
                                        } else {
                                                selectedIds.delete( id );
                                                $( this ).prop( 'checked', false );
                                        }
                                } );
                                if ( ! checked ) {
                                        selectAllMatching = false;
                                }
                                storeSelection();
                                updateSelectionNote();
                        } );

                        $( '#ele2gb-select-all-matching' ).on( 'click', function() {
                                selectAllMatching = true;
                                updateSelectionNote();
                                goToStep( 2 );
                        } );

                        $( '#ele2gb-step1-next' ).on( 'click', function() {
                                if ( ! selectAllMatching && selectedIds.size === 0 ) {
                                        window.alert( strings.noSelection || '' );
                                        return;
                                }
                                goToStep( 2 );
                        } );

                        $( '#ele2gb-step2-next' ).on( 'click', function() {
                                optionsState = collectOptions();
                                renderSummary();
                                goToStep( 3 );
                        } );

                        $( '[data-ele2gb-back]' ).on( 'click', function() {
                                const target = parseInt( $( this ).data( 'ele2gb-back' ), 10 );
                                if ( ! Number.isNaN( target ) ) {
                                        goToStep( target );
                                }
                        } );

                        startButton.on( 'click', function() {
                                if ( queueState && queueState.pending && queueState.pending.length && ! queueState.completed && ! isProcessing ) {
                                        isProcessing = true;
                                        cancelButton.prop( 'disabled', false );
                                        processNext();
                                } else {
                                        startQueue();
                                }
                        } );

                        cancelButton.on( 'click', function() {
                                cancelQueue();
                        } );

                        exportButton.on( 'click', function() {
                                exportResults();
                        } );
                }

                loadSelection();
                syncCheckboxes();
                updateSelectionNote();
                attachEvents();

                if ( data.queue && data.queue.id ) {
                        queueState = data.queue;
                        optionsState = queueState.options || {};
                        selectAllMatching = !! queueState.select_all;

                        if ( queueState.completed ) {
                                renderSummary();
                                renderResults();
                                updateProgress();
                                goToStep( 3 );
                                exportButton.prop( 'disabled', false );
                                startButton.text( strings.startButton || defaultStartLabel );
                        } else {
                                resumeExistingQueue();
                        }
                }
        } );
})( jQuery );
