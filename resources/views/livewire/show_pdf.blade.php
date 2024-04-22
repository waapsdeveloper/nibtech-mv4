<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print PDF</title>
    <style>
        *{
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    {{-- <iframe id="pdfFrame" style="display:block; width:100%; height:97.5vh;" src="{{ $delivery_note }}"></iframe> --}}
    {{-- <embed src="{{ $delivery_note }}" width="500" height="375" type="application/pdf"> --}}
    <script>
        // Print the PDF when the page loads
        window.onload = function() {
            setTimeout(function() { window.close();}, 500);

            // Convert the base64-encoded PDF content to Uint8Array
            var pdfContentBase64 = "{!! base64_encode($pdfContent) !!}";
            var pdfContentUint8Array = Uint8Array.from(atob(pdfContentBase64), c => c.charCodeAt(0));

            // Create a Blob object from the Uint8Array with MIME type 'application/pdf'
            var blob = new Blob([pdfContentUint8Array], { type: 'application/pdf' });

            // Create a URL for the Blob
            var url = URL.createObjectURL(blob);

            // Open the PDF in a new window/tab
            var pdfWindow = window.open(url);

            // Handle the onload event of the new window/tab
            pdfWindow.onload = function() {
                // Once the PDF is fully loaded, trigger the print dialogue
                pdfWindow.print();

                // Close the window after printing
                setTimeout(function() {
                    pdfWindow.close();
                    window.close();
                }, 50000);
            };

        };
    </script>
</body>
</html>
