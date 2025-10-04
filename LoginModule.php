<?php
session_start();
if (!empty($_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin') {
    header('Location: admin_listings.php'); exit;
  } elseif ($_SESSION['role'] === 'unit_owner') {
    header('Location: DashboardUO.php'); exit;
  } else {
    header('Location: DashboardT.php'); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HanapBahay Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="styles_login.css" />
  <style>
    .password-wrapper {
      position: relative;
    }

    .password-wrapper input {
      padding-right: 40px; /* prevent text from going under icon */
    }

    .toggle-icon {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 1.2rem;
      color: #999;
      z-index: 10;
    }

    .toggle-icon:hover {
      color: #ff914d;
    }
  </style>
</head>
<body class="bg-soft">
  <div class="login-container">
    <img src="PROTOTYPE1.png" alt="HanapBahay Logo" class="logo mb-2">
    <h1 class="brand-name">HANAPBAHAY</h1>
    <p class="tagline">"Finding your way home"</p>

    <?php if (isset($_SESSION['login_error'])): ?>
      <div class="alert alert-danger text-center">
        <?= htmlspecialchars($_SESSION['login_error']) ?>
        <?php if ($_SESSION['login_error'] === 'Please verify your email before logging in.'): ?>
          <br><small>Didn’t receive the email? <a href="resend_verification.php">Resend verification</a></small>
        <?php endif; unset($_SESSION['login_error']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_flash'])): ?>
      <div class="alert alert-success text-center">
        <?= htmlspecialchars($_SESSION['success_flash']); unset($_SESSION['success_flash']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['reg_errors'])): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($_SESSION['reg_errors'] as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; unset($_SESSION['reg_errors']); ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="toggle-btns">
      <button id="showLogin" class="toggle-btn active">Login</button>
      <button id="showRegister" class="toggle-btn">Register</button>
    </div>

    <div class="forms">
      <!-- ✅ LOGIN -->
      <form id="loginForm" class="form active" method="post" action="login_process">
        <input type="email" class="form-control" name="email" placeholder="Email" required>
        <div class="password-wrapper">
          <input type="password" class="form-control" name="password" id="loginPassword" placeholder="Password" required>
          <span class="toggle-icon" onclick="togglePassword('loginPassword', this)">👁️</span>
        </div>
        <div class="text-center mb-2">
          <a href="forgot_password.php" style="color: #ff914d; text-decoration: none; font-size: 0.9rem;">Forgot Password?</a>
        </div>
        <button type="submit" class="btn">Login</button>

        <div class="text-center mt-3">
          <a href="index.php" class="btn">Return to Homepage</a>
        </div>
      </form>

      <!-- ✅ REGISTER -->
      <form id="registerForm" class="form" method="post" action="register_process">
        <input type="text" class="form-control" name="first_name" placeholder="First Name" required>
        <input type="text" class="form-control" name="last_name" placeholder="Last Name" required>
        <input type="email" class="form-control" name="email" placeholder="Email" required>

        <div class="password-wrapper">
          <input type="password" class="form-control" name="password" id="regPassword" placeholder="Password" required>
          <span class="toggle-icon" onclick="togglePassword('regPassword', this)">👁️</span>
        </div>

        <div class="password-wrapper">
          <input type="password" class="form-control" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required>
          <span class="toggle-icon" onclick="togglePassword('confirmPassword', this)">👁️</span>
        </div>

        <select class="form-control" name="role" required>
          <option value="">Select Role</option>
          <option value="tenant">Tenant</option>
          <option value="unit_owner">Unit Owner</option>
        </select>

        <button type="submit" class="btn mt-4">Register</button>
      </form>
    </div>
  </div>

  <script>
    const showLogin = document.getElementById('showLogin');
    const showRegister = document.getElementById('showRegister');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    showLogin.onclick = () => {
      loginForm.classList.add('active');
      registerForm.classList.remove('active');
      showLogin.classList.add('active');
      showRegister.classList.remove('active');
    };

    showRegister.onclick = () => {
      registerForm.classList.add('active');
      loginForm.classList.remove('active');
      showRegister.classList.add('active');
      showLogin.classList.remove('active');
    };

    function togglePassword(id, el) {
      const input = document.getElementById(id);
      const isVisible = input.type === "text";
      input.type = isVisible ? "password" : "text";
      el.textContent = isVisible ? "👁️" : "🔒";
    }
  </script>
</body>
</html>
