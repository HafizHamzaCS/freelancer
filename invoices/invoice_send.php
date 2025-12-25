<?php
require_once '../config.php';
require_once '../functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice = db_fetch_one("SELECT i.*, c.name as client_name, c.email as client_email 
                         FROM invoices i 
                         LEFT JOIN clients c ON i.client_id = c.id 
                         WHERE i.id = $id");

if (!$invoice) {
    redirect('invoices/invoice_list.php');
}

// Handle Send
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $to = escape($_POST['to']);
    $subject = escape($_POST['subject']);
    $message = escape($_POST['message']);
    
    // Mock Send Email
    // In a real app: mail($to, $subject, $message);
    
    // Log Email
    $sent_at = date('Y-m-d H:i:s');
    $sql = "INSERT INTO email_logs (client_id, subject, sent_at) VALUES ({$invoice['client_id']}, '$subject', '$sent_at')";
    mysqli_query($conn, $sql);
    
    // Update Invoice Status if needed (optional, maybe keep as Unpaid until paid)
    // mysqli_query($conn, "UPDATE invoices SET status = 'Sent' WHERE id = $id");

    $success = "Invoice sent successfully to $to!";
}

require_once '../header.php';

// Default Template
$subject = "Invoice #{$invoice['invoice_number']} from " . APP_NAME;
$message = "Hi {$invoice['client_name']},\n\nPlease find attached invoice #{$invoice['invoice_number']} for " . format_money($invoice['amount']) . ".\n\nDue Date: {$invoice['due_date']}\n\nThank you for your business!\n\nBest regards,\n" . APP_NAME;
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Send Invoice</h2>
        <a href="invoice_list.php" class="btn btn-ghost">Cancel</a>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert alert-success mb-6">
        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span><?php echo $success; ?></span>
        <div>
            <a href="invoice_list.php" class="btn btn-sm">Back to Invoices</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST">
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">To</span></label>
                    <input type="email" name="to" value="<?php echo htmlspecialchars($invoice['client_email']); ?>" class="input input-bordered w-full" required />
                </div>
                
                <div class="form-control w-full mb-4">
                    <label class="label"><span class="label-text">Subject</span></label>
                    <input type="text" name="subject" value="<?php echo htmlspecialchars($subject); ?>" class="input input-bordered w-full" required />
                </div>
                
                <div class="form-control w-full mb-6">
                    <label class="label"><span class="label-text">Message</span></label>
                    <textarea name="message" class="textarea textarea-bordered h-48" required><?php echo htmlspecialchars($message); ?></textarea>
                </div>
                
                <div class="flex justify-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                        Send Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
