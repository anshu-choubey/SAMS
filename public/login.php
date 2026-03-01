<?php
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS - Admin Login</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .login-header h3 {
            margin: 0;
            font-weight: 600;
        }

        .login-header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: #fff;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        .login-footer {
            text-align: center;
            padding: 20px 30px 30px;
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-shield-check"></i>
                <h3>SAMS Admin</h3>
                <p>Administrator Login Portal</p>
            </div>
            
            <div class="login-body">
                <div id="alertContainer"></div>
                
                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </form>
                
                <hr class="my-4">
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; font-size: 0.9rem;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #667eea;"><i class="bi bi-info-circle"></i> Admin Login</p>
                    <div>
                        <strong>Admin:</strong><br>
                        Email: admin@sams.edu<br>
                        Password: Admin@123
                    </div>
                </div>
            </div>
            
            <div class="login-footer">
                <p>&copy; 2026 SAMS. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Handle login form submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            try {
                const response = await fetch('/api/public/login-test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email, password }),
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Only allow admin login
                    if (result.data.user.role === 'admin') {
                        showAlert('success', 'Login successful! Redirecting...');
                        
                        setTimeout(() => {
                            window.location.href = 'admin/index.php';
                        }, 1000);
                    } else {
                        showAlert('error', 'Access denied. Only administrators can login.');
                    }
                } else {
                    showAlert('error', result.message || 'Login failed');
                }
            } catch (error) {
                showAlert('error', 'Login failed. Please try again.');
            }
        });

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    </script>
</body>
</html>
