<?php
$status = $_GET['status'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
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
        <img src="img/login.jpg" class="img-fluid" alt="Login illustration"
             style="width:85%; height:auto; object-fit:cover; border-radius:30px 30px 30px 30px;">
      </div>

      <!-- Right side login form -->
      <div class="col-md-6 d-flex align-items-center">
        <div class="w-75 mx-auto">
          <main>
            <div class="mb-4">
              <h1 class="fw-bold">Welcome to <span class="text-primary">ChildSafe</span></h1>
              <h5 class="text-secondary">Sign in to your account below</h5>
            </div>

            <!-- Feedback messages -->
            <?php if ($status === "success"): ?>
              <div class="alert alert-success">Registration successful!</div>
            <?php elseif ($status === "error"): ?>
              <div class="alert alert-danger">Invalid username or password.</div>
            <?php endif; ?>

            <!-- Login form -->
            <form action="server-login.php" method="POST">
              <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" name="username" id="username" required>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" name="password" id="password" required>
              </div>

              <div class="my-4">
                <button class="btn btn-primary w-100" type="submit" name="login">Login</button>
              </div>

              <p class="text-muted text-center" style="font-size: small;">
                Don't have an account yet? <a href="signup.php">Sign Up</a>.
              </p>
            </form>
          </main>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
