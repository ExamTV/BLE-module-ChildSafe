<?php
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");
    body {
      font-family: "Poppins", sans-serif;
      background-color: #f8f9fa;
    }
    .row {
      height: 100vh;
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Left side image -->
      <div class="col-md-6 d-none d-md-flex justify-content-center align-items-center p-0">
        <img src="img/register.jpg" class="img-fluid" alt="Signup illustration"
             style="width:100%; height:auto; object-fit:cover; border-radius:0 30px 30px 0;">
      </div>

      <!-- Right side signup form -->
      <div class="col-md-6 d-flex align-items-center">
        <div class="w-75 mx-auto">
          <main>
            <div class="mb-4">
              <h1 class="fw-bold">Create your <span class="text-primary">ChildSafe</span> account</h1>
              <h5 class="text-secondary">Fill in your details below</h5>
            </div>

            <!-- Feedback messages -->
            <?php if ($status === "exists"): ?>
              <div class="alert alert-warning">Username or email already exists.</div>
            <?php elseif ($status === "error"): ?>
              <div class="alert alert-danger">Registration failed. Please try again.</div>
            <?php endif; ?>

            <!-- Signup form -->
            <form action="server-signup.php" method="POST">
              <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" name="username" id="username" required>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" name="password" id="password" required>
              </div>

              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" name="email" id="email" required>
              </div>

              <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" name="phone" id="phone" required>
              </div>

              <div class="my-4">
                <button class="btn btn-primary w-100" type="submit" name="signup">Sign Up</button>
              </div>

              <p class="text-muted text-center" style="font-size: small;">
                Already a member? <a href="login.php">Sign in</a>.
              </p>
            </form>
          </main>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
