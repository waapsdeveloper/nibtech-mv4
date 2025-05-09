<!DOCTYPE html>
<html>
<head>
    <title>Print Labels</title>
</head>
<body style="margin:0" onload="triggerPrint()">
    <embed id="pdfViewer" src="{{ asset('storage/orders.pdf') }}" type="application/pdf" width="100%" height="100%" />
    <script>
        function triggerPrint() {
            const win = window.open(document.getElementById('pdfViewer').src, '_blank');
            win.addEventListener('load', () => {
                win.print();
            });
        }
    </script>
</body>
</html>
