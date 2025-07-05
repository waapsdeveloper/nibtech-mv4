
    /// Authentication setup ///
    qz.security.setCertificatePromise(function(resolve, reject) {
        //Preferred method - from server
       fetch("/qz-certs/certificate.crt", {cache: 'no-store', headers: {'Content-Type': 'text/plain'}})
         .then(function(data) { data.ok ? resolve(data.text()) : reject(data.text()); });

    });

    qz.security.setSignatureAlgorithm("SHA512"); // Since 2.1

    // qz.security.setSignaturePromise(toSign => {
    //     const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    //     return fetch('/qz-sign', {
    //         method: 'GET',
    //         body: JSON.stringify({ string: toSign })
    //     }).then(res => res.text());
    // });
    qz.security.setSignaturePromise(function(toSign) {
        return function(resolve, reject) {
            //Preferred method - from server
           fetch("/qz-sign?request=" + toSign, {cache: 'no-store', headers: {'Content-Type': 'text/plain'}})
             .then(function(data) { data.ok ? resolve(data.text()) : reject(data.text()); });

            //Alternate method - unsigned
            // resolve(); // remove this line in live environment
        };
    });


    /// Connection ///
    function launchQZ() {
        if (!qz.websocket.isActive()) {
            window.location.assign("qz:launch");
            //Retry 5 times, pausing 1 second between each attempt
            startConnection({ retries: 5, delay: 1 });
        }
    }

    function startConnection(config) {
        var host = "localhost"; // Default host
        var usingSecure = $("#connectionUsingSecure").prop('checked');

        // Connect to a print-server instance, if specified
        if (host != "" && host != 'localhost') {
            if (config) {
                config.host = host;
                config.usingSecure = usingSecure;
            } else {
                config = { host: host, usingSecure: usingSecure };
            }
        }

        if (!qz.websocket.isActive()) {
            updateState('Waiting', 'default');

            qz.websocket.connect(config).then(function() {
                updateState('Active', 'success');
                findVersion();
            }).catch(handleConnectionError);
        } else {
            displayMessage('An active connection with QZ already exists.', 'alert-warning');
        }
    }

    function endConnection() {
        if (qz.websocket.isActive()) {
            qz.websocket.disconnect().then(function() {
                updateState('Inactive', 'default');
            }).catch(handleConnectionError);
        } else {
            displayMessage('No active connection with QZ exists.', 'alert-warning');
        }
    }


    /// Detection ///
    function findPrinter(query, set, radio, type) {
        $("#printerSearch").val(query);
        qz.printers.find(query).then(function(data) {
            displayMessage("<strong>Found:</strong> " + data);
            if (type) {
                localStorage.setItem(type + "_Printer", data);
                displayMessage("Printer for " + type + " set to: " + data, 'alert-success');
            }
            if (set) { setPrinter(data); }
            if(radio) {
                var input = document.querySelector("input[value='" + radio + "']");
                if(input) {
                    input.checked = true;
                    $(input.parentElement).fadeOut(300).fadeIn(500);
                }

            }
        }).catch(displayError);
    }

    function findDefaultPrinter(set) {
        qz.printers.getDefault().then(function(data) {
            displayMessage("<strong>Found:</strong> " + data);
            if (set) { setPrinter(data); }
        }).catch(displayError);
    }

    function findPrinters() {
        qz.printers.find().then(function(data) {
            var list = '';
            for(var i = 0; i < data.length; i++) {
                var button = `<button class="btn btn-default btn-xs" onclick="findPrinter(&quot;${data[i].replace(/\\/g, "\\\\")}&quot;, true, null, 'A4')" data-dismiss="alert">Use This for A4</button><button class="btn btn-default btn-xs" onclick="findPrinter(&quot;${data[i].replace(/\\/g, "\\\\")}&quot;, true, null, 'DHL')" data-dismiss="alert">Use This for DHL</button><button class="btn btn-default btn-xs" onclick="findPrinter(&quot;${data[i].replace(/\\/g, "\\\\")}&quot;, true, null, 'Sticker')" data-dismiss="alert">Use This for Sticker</button>`;
                list += "&nbsp; " + data[i] + "&nbsp;" + button + "<br/>";
            }

            displayMessage("<strong>Available printers:</strong><br/>" + list, null, 15000);
        }).catch(displayError);
    }

    function detailPrinters() {
        qz.printers.details().then(function(data) {
            var list = '';
            for(var i = 0; i < data.length; i++) {
                list += "<li>" + (data[i].default ? "* " : "") + data[i].name + "<ul>" +
                    "<li><strong>Driver:</strong> " + data[i].driver + "</li>" +
                    "<li><strong>Density:</strong> " + data[i].density + "dpi</li>" +
                    "<li><strong>Connection:</strong> " + data[i].connection + "</li>" +
                    (data[i].trays ? "<li><strong>Trays:</strong> " + data[i].trays + "</li>" : "") +
                    accumulateSizes(data[i]) +
                    "</ul></li>";
            }

            pinMessage("<strong>Printer details:</strong><br/><ul>" + list + "</ul>");
        }).catch(displayError);
    }

    function updateState(text, css) {
        $("#qz-status").html(text);
        $("#qz-connection").removeClass().addClass('panel panel-' + css);

        if (text === "Inactive" || text === "Error") {
            $("#launch").show();
        } else {
            $("#launch").hide();
        }
    }

    /// Helpers ///
    function handleConnectionError(err) {
        updateState('Error', 'danger');

        if (err.target != undefined) {
            if (err.target.readyState >= 2) { //if CLOSING or CLOSED
                displayError("Connection to QZ Tray was closed");
            } else {
                displayError("A connection error occurred, check log for details");
                console.error(err);
            }
        } else {
            displayError(err);
        }
    }

    function displayError(err) {
        console.error(err);
        displayMessage(err, 'alert-danger');
    }

    function displayMessage(msg, css, time) {
        if (css == undefined) { css = 'alert-info'; }

        var timeout = setTimeout(function() { $('#' + timeout).alert('close'); }, time ? time : 5000);

        var alert = $("<div/>").addClass('alert alert-dismissible ' + css)
            .css('max-height', '20em').css('overflow', 'auto')
            .attr('id', timeout).attr('role', 'alert');
        alert.html("<button type='button' class='close' data-dismiss='alert'>&times;</button>" + msg);

        $("#qz-alert").append(alert);
    }

    var qzVersion = 0;
    function findVersion() {
        qz.api.getVersion().then(function(data) {
            $("#qz-version").html(data);
            qzVersion = data;
        }).catch(displayError);
    }

    function setPrinter(printer) {
        var cf = getUpdatedConfig();
        cf.setPrinter(printer);

        if (printer && typeof printer === 'object' && printer.name == undefined) {
            var shown;
            if (printer.file != undefined) {
                shown = "<em>FILE:</em> " + printer.file;
            }
            if (printer.host != undefined) {
                shown = "<em>HOST:</em> " + printer.host + ":" + printer.port;
            }

            $("#configPrinter").html(shown);
        } else {
            if (printer && printer.name != undefined) {
                printer = printer.name;
            }

            if (printer == undefined) {
                printer = 'NONE';
            }
            $("#configPrinter").html(printer);
        }
    }

    /// QZ Config ///
    var cfg = null;
    function getUpdatedConfig(cleanConditions) {
        if (cfg == null) {
            cfg = qz.configs.create(null);
        }

        updateConfig(cleanConditions || {});
        return cfg
    }

    function updateConfig(cleanConditions) {
        var pxlSize = null;
        if (isChecked($("#pxlSizeActive"), cleanConditions['pxlSizeActive']) && (($("#pxlSizeWidth").val() !== '') || ($("#pxlSizeHeight").val() !== ''))) {
            pxlSize = {
                width: $("#pxlSizeWidth").val(),
                height: $("#pxlSizeHeight").val()
            };
        }

        var pxlBounds = null;
        if (isChecked($("#pxlBoundsActive"), cleanConditions['pxlBoundsActive'])) {
            pxlBounds = {
                x: $("#pxlBoundX").val(),
                y: $("#pxlBoundY").val(),
                width: $("#pxlBoundWidth").val(),
                height: $("#pxlBoundHeight").val()
            };
        }

        var pxlDensity = includedValue($("#pxlDensity"));
        if (isChecked($("#pxlDensityAsymm"), cleanConditions['pxlDensityAsymm'])) {
            pxlDensity = {
                cross: $("#pxlCrossDensity").val(),
                feed: $("#pxlFeedDensity").val()
            };
        }

        var pxlMargins = includedValue($("#pxlMargins"));
        if (isChecked($("#pxlMarginsActive"), cleanConditions['pxlMarginsActive'])) {
            pxlMargins = {
                top: $("#pxlMarginsTop").val(),
                right: $("#pxlMarginsRight").val(),
                bottom: $("#pxlMarginsBottom").val(),
                left: $("#pxlMarginsLeft").val()
            };
        }

        var copies = 1;
        var spoolSize = null;
        var jobName = null;
        if ($("#rawTab").hasClass("active")) {
            copies = includedValue($("#rawCopies"));
            spoolSize = includedValue($("#rawSpoolSize"));
            jobName = includedValue($("#rawJobName"));
        } else {
            copies = includedValue($("#pxlCopies"));
            spoolSize = includedValue($("#pxlSpoolSize"));
            jobName = includedValue($("#pxlJobName"));
        }

        cfg.reconfigure({
                            forceRaw: includedValue($("#rawForceRaw"), isChecked($("#rawForceRaw"), cleanConditions['rawForceRaw'])),
                            encoding: includedValue($("#rawEncoding")),
                            spool: { size: spoolSize, end: includedValue($("#rawSpoolEnd")) },

                            bounds: pxlBounds,
                            colorType: includedValue($("#pxlColorType")),
                            copies: copies,
                            density: pxlDensity,
                            duplex: includedValue($("#pxlDuplex")),
                            interpolation: includedValue($("#pxlInterpolation")),
                            jobName: jobName,
                            margins: pxlMargins,
                            orientation: includedValue($("#pxlOrientation")),
                            paperThickness: includedValue($("#pxlPaperThickness")),
                            printerTray: includedValue($("#pxlPrinterTray")),
                            rasterize: includedValue($("#pxlRasterize"), isChecked($("#pxlRasterize"), cleanConditions['pxlRasterize'])),
                            rotation: includedValue($("#pxlRotation")),
                            scaleContent: includedValue($("#pxlScale"), isChecked($("#pxlScale"), cleanConditions['pxlScale'])),
                            size: pxlSize,
                            units: includedValue($("input[name='pxlUnits']:checked"))
                        });
    }

    function isChecked(checkElm, ifClean) {
        if (!checkElm.hasClass("dirty")) {
            if (ifClean !== undefined) {
                var lbl = checkElm.siblings("label").text();
                displayMessage("Forced " + lbl + " " + ifClean + ".", 'alert-warning');

                return ifClean;
            }
        }

        return checkElm.prop("checked");
    }

    function includedValue(element, value) {
        if (value != null) {
            return value;
        } else if (element.hasClass("dirty")) {
            return element.val();
        } else {
            return undefined;
        }
    }

    function accumulateSizes(data) {
        var html = "";
        if(data.sizes) {
            var html = "<li><details><summary><strong><u>Sizes:</u></strong> (" + data.sizes.length + ")</summary> ";
            var sizes = data.sizes;
            html += "<ul>";
            for(var i = 0; i < sizes.length; i++) {
                html += "<li><details><summary><u>" + sizes[i].name + "</u></summary><ul>";

                var inch = sizes[i].in.width + " x " + sizes[i].in.height;
                var mill = sizes[i].mm.width + " x " + sizes[i].mm.height;

                var inchTrunc = truncate(sizes[i].in.width, 3) + "&nbsp;x&nbsp;" + truncate(sizes[i].in.height, 3);
                var millTrunc = truncate(sizes[i].mm.width, 3) + "&nbsp;x&nbsp;" + truncate(sizes[i].mm.height, 3);

                html += "<li style='text-overflow: ellipsis;' title='" + inch + "'><strong>in:</strong>&nbsp;" + inchTrunc + "</li>";
                html += "<li style='text-overflow: ellipsis;' title='" + mill + "'><strong>mm:</strong>&nbsp;" + millTrunc + "</li>";

                html += "</ul></details></li>";
            }
            html += "</ul></details></li>";
        }
        return html;
    }


    function getUpdatedOptions(onlyPixel) {
        if (onlyPixel) {
            return {
                pageWidth: $("#pPxlWidth").val(),
                pageHeight: $("#pPxlHeight").val(),
                pageRanges: $("#pPxlRange").val(),
                ignoreTransparency: $("#pPxlTransparent").prop('checked'),
                altFontRendering: $("#pPxlAltFontRendering").prop('checked')
            };
        } else {
            return {
                language: $("input[name='pLanguage']:checked").val(),
                x: $("#pX").val(),
                y: $("#pY").val(),
                dotDensity: $("#pDotDensity").val(),
                xmlTag: $("#pXml").val(),
                pageWidth: $("#pRawWidth").val(),
                pageHeight: $("#pRawHeight").val()
            };
        }
    }

    function pinMessage(msg, id, css) {
        if (css == undefined) { css = 'alert-info'; }

        var alert = $("<div/>").addClass('alert alert-dismissible ' + css)
            .css('max-height', '20em').css('overflow', 'auto').attr('role', 'alert')
            .html("<button type='button' class='close' data-dismiss='alert'>&times;</button>");

        var text = $("<div/>").html(msg);
        if (id != undefined) { text.attr('id', id); }

        alert.append(text);

        console.log("Pinning message: " + msg);
        $("#qz-pin").append(alert);
    }
    function truncate(val, length, ellipsis) {
        var truncated;
        if(isNaN(val)) {
            truncated = val.substring(0, length);
        } else {
            var mult = Math.pow(10, length);
            truncated = Math.floor(val * mult) / mult;
        }
        if(ellipsis === false) {
            return truncated;
        }
        return val === truncated ? val : truncated + "&hellip;";
    }
    function printQz(link, type) {
        console.log("Printing from link: " + link);
        const printer = localStorage.getItem(type + "_Printer");
        if (!printer) {
            displayMessage("No printer selected for " + type + ". Please select a printer first.", 'alert-warning');
            return;
        }
        const config = qz.configs.create(printer, { orientation: 'portrait' });

        fetch(link)
            .then(response => response.blob())
            .then(blob => blob.arrayBuffer())
            .then(arrayBuffer => {
                return qz.print(config, [{
                    type: 'pixel',
                    format: 'pdf',
                    flavor: 'file',
                    data: storagePath + '/sticker_print.pdf',
                }]);
            })
            .then(() => {
                console.log("✅ Printed successfully.");
            })
            .catch(err => {
                console.error("❌ Print failed", err);
                displayMessage("Print failed: " + (err && err.message ? err.message : err), 'alert-danger');
            });
    }



    $(document).ready(function() {


        startConnection();

    });
