<?php
require '../vendor/autoload.php';

$validate_from = filter_input(INPUT_POST, 'from', FILTER_VALIDATE_EMAIL);
$validate_to = filter_input(INPUT_POST, 'to', FILTER_VALIDATE_EMAIL);

$from = filter_input(INPUT_POST, 'from', FILTER_SANITIZE_EMAIL);
$to = filter_input(INPUT_POST, 'to', FILTER_SANITIZE_EMAIL);
$subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
$message = filter_input(INPUT_POST, 'message');
$error = '';
$success = FALSE;

if (count(array_filter([$from, $to, $subject, $message])) === 4 && $validate_from && $validate_to) {
  try {
    $mailer = new \zviryatko\nomail\Sendmail(
      getenv('PROJECT_BASE_URL'),
      getenv('SMTP_ADDRESS'),
      getenv('SMTP_PORT'),
    );
    $success = $mailer->mail($from, $to, $subject, $message) === 0;
    if ($success) {
      header('Location: /?success=1');
      exit();
    }
  }
  catch (\Exception $e) {
    $error = $e->getMessage();
  }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/bower-asset/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">

  <title>NoMail test</title>
</head>
<body>

<div class="container">
  <h1>NoMail test</h1>
  <hr>

  <?php if (!empty($_POST) && !empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
  <?php elseif (!empty($_GET['success'])): ?>
    <div class="alert alert-success" role="alert">Email successfully sent!</div>
  <?php endif; ?>

<form method="post">

  <div class="mb-3">
    <label for="from" class="form-label">From</label>
    <input type="email" required class="form-control<?php if (!empty($from) && !$validate_from) echo ' is-invalid' ?>" id="from" name="from" value="<?php echo $from; ?>" placeholder="sender@example.com">
    <?php if (!empty($from) && !$validate_from): ?><div id="email" class="invalid-feedback">Email address is not correct</div><?php endif; ?>
  </div>

  <div class="mb-3">
    <label for="to" class="form-label">To</label>
    <input type="email" required class="form-control<?php if (!empty($to) && !$validate_to) echo ' is-invalid' ?>" id="to" name="to" value="<?php echo $to; ?>" placeholder="recepient@example.com">
    <?php if (!empty($to) && !$validate_to): ?><div id="to" class="invalid-feedback">Email address is not correct</div><?php endif; ?>
  </div>

  <div class="mb-3">
    <label for="subject" class="form-label">Subject</label>
    <input maxlength="255" class="form-control<?php if (!empty($_POST) && empty($subject)) echo ' is-invalid' ?>" id="subject" name="subject" value="<?php echo filter_var($subject, FILTER_SANITIZE_ADD_SLASHES); ?>" placeholder="Email subject">
    <?php if (!empty($_POST) && empty($subject)): ?><div id="to" class="invalid-feedback">Subject shouldn't be empty</div><?php endif; ?>
  </div>

  <div class="mb-3">
    <label for="message" class="form-label">Email message</label>
    <textarea class="form-control<?php if (!empty($_POST) && empty($message)) echo ' is-invalid' ?>" id="message" name="message" rows="3" placeholder="Dear friend, writing you these lines..."><?php echo filter_var($message, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></textarea>
    <?php if (!empty($_POST) && empty($message)): ?><div id="to" class="invalid-feedback">Message shouldn't be empty</div><?php endif; ?>

  </div>

  <div class="col-12">
    <button class="btn btn-primary" type="submit">Sent</button>
  </div>
</form>

</div>

<script src="/assets/bower-asset/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/bower-asset/tinymce/tinymce.min.js"></script>

<script>
  tinymce.init({
    selector: 'textarea#message',
    menubar: false
  });
</script>

</body>
</html>
