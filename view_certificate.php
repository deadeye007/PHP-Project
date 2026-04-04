<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    header('Location: profile.php');
    exit;
}

// Verify the certificate belongs to the user
global $pdo;
$stmt = $pdo->prepare("SELECT uc.*, c.title, c.description FROM user_certificates uc JOIN certificates c ON uc.certificate_id = c.id WHERE uc.id = ? AND uc.user_id = ?");
$stmt->execute([$certificate_id, $user_id]);
$certificate = $stmt->fetch();

if (!$certificate) {
    header('Location: profile.php');
    exit;
}

$title = 'Certificate: ' . htmlspecialchars($certificate['title']);

// Generate certificate HTML
$certificate_html = generateCertificateHTML($certificate['certificate_id'], $user_id);

include 'includes/header.php';
?>

<main class="container my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?php echo htmlspecialchars($certificate['title']); ?></h1>
                <div>
                    <a href="profile.php" class="btn btn-outline-secondary">Back to Profile</a>
                    <button onclick="printCertificate()" class="btn btn-primary">Print Certificate</button>
                    <button onclick="downloadCertificate()" class="btn btn-success">Download PDF</button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div id="certificate-content" style="max-width: 800px; margin: 0 auto; padding: 20px; border: 2px solid #333; background: white;">
                        <?php echo $certificate_html; ?>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <h5>Certificate Details</h5>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($certificate['description']); ?></p>
                <p><strong>Awarded:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($certificate['awarded_at'])); ?></p>
                <p><strong>Verification Code:</strong> <code><?php echo htmlspecialchars($certificate['verification_code']); ?></code></p>
                <p class="text-muted small">Use the verification code to confirm the authenticity of this certificate.</p>
            </div>
        </div>
    </div>
</main>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #certificate-content, #certificate-content * {
        visibility: visible;
    }
    #certificate-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: none;
        border: none;
        box-shadow: none;
    }
}
</style>

<script>
function printCertificate() {
    window.print();
}

function downloadCertificate() {
    // For now, we'll use a simple approach. In a production environment,
    // you'd want to use a proper PDF generation library like TCPDF or Dompdf
    const certificateContent = document.getElementById('certificate-content').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Certificate</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                    @media print { body { margin: 0; } }
                </style>
            </head>
            <body>
                ${certificateContent}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include 'includes/header.php'; ?></content>
<parameter name="filePath">/Users/asturm/Projects/PHP Project/certificate.php