package com.sams.app.ui.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.sams.app.data.api.ApiClient
import com.sams.app.data.models.UserRole
import com.sams.app.data.repository.AuthRepository
import com.sams.app.databinding.ActivityLoginBinding
import com.sams.app.ui.student.StudentActivity
import com.sams.app.ui.teacher.TeacherActivity
import kotlinx.coroutines.launch

/**
 * Login Activity
 * Handles user authentication
 */
class LoginActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityLoginBinding
    private val authRepository = AuthRepository()
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Check if already logged in
        if (authRepository.isLoggedIn()) {
            navigateToHome()
            return
        }
        
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)
        
        setupUI()
    }
    
    private fun setupUI() {
        binding.btnLogin.setOnClickListener {
            val email = binding.etEmail.text.toString().trim()
            val password = binding.etPassword.text.toString()
            
            if (validateInput(email, password)) {
                performLogin(email, password)
            }
        }
    }
    
    private fun validateInput(email: String, password: String): Boolean {
        if (email.isEmpty()) {
            binding.tilEmail.error = "Email is required"
            return false
        }
        binding.tilEmail.error = null
        
        if (password.isEmpty()) {
            binding.tilPassword.error = "Password is required"
            return false
        }
        binding.tilPassword.error = null
        
        return true
    }
    
    private fun performLogin(email: String, password: String) {
        showLoading(true)
        
        lifecycleScope.launch {
            authRepository.login(email, password)
                .onSuccess { loginData ->
                    showLoading(false)
                    
                    // Store session token (done in repository)
                    Toast.makeText(this@LoginActivity, "Login successful", Toast.LENGTH_SHORT).show()
                    
                    // Register FCM token
                    registerFcmToken()
                    
                    // Navigate based on role
                    navigateToHome()
                }
                .onFailure { error ->
                    showLoading(false)
                    Toast.makeText(this@LoginActivity, error.message, Toast.LENGTH_LONG).show()
                }
        }
    }
    
    private fun registerFcmToken() {
        val fcmToken = getSharedPreferences("sams_prefs", MODE_PRIVATE)
            .getString("fcm_token", null)
        
        if (fcmToken != null) {
            lifecycleScope.launch {
                authRepository.registerFcmToken(fcmToken, android.os.Build.MODEL)
            }
        }
    }
    
    private fun navigateToHome() {
        val role = authRepository.getUserRole()
        
        val intent = when (role) {
            UserRole.STUDENT.value -> Intent(this, StudentActivity::class.java)
            UserRole.TEACHER.value -> Intent(this, TeacherActivity::class.java)
            else -> {
                // Admin not supported in mobile app
                Toast.makeText(this, "Admin access via web only", Toast.LENGTH_LONG).show()
                ApiClient.clearSession()
                return
            }
        }
        
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        startActivity(intent)
        finish()
    }
    
    private fun showLoading(show: Boolean) {
        binding.progressBar.visibility = if (show) View.VISIBLE else View.GONE
        binding.btnLogin.isEnabled = !show
    }
}

// ==================== Sample Layout (activity_login.xml) ====================
/*
<?xml version="1.0" encoding="utf-8"?>
<androidx.constraintlayout.widget.ConstraintLayout
    xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:app="http://schemas.android.com/apk/res-auto"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:padding="24dp">

    <TextView
        android:id="@+id/tvTitle"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:text="SAMS"
        android:textSize="32sp"
        android:textStyle="bold"
        app:layout_constraintTop_toTopOf="parent"
        app:layout_constraintStart_toStartOf="parent"
        app:layout_constraintEnd_toEndOf="parent"
        android:layout_marginTop="64dp"/>

    <TextView
        android:id="@+id/tvSubtitle"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:text="Smart Attendance Management System"
        android:textSize="14sp"
        app:layout_constraintTop_toBottomOf="@id/tvTitle"
        app:layout_constraintStart_toStartOf="parent"
        app:layout_constraintEnd_toEndOf="parent"
        android:layout_marginTop="8dp"/>

    <com.google.android.material.textfield.TextInputLayout
        android:id="@+id/tilEmail"
        style="@style/Widget.MaterialComponents.TextInputLayout.OutlinedBox"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        android:hint="Email"
        app:layout_constraintTop_toBottomOf="@id/tvSubtitle"
        android:layout_marginTop="48dp">

        <com.google.android.material.textfield.TextInputEditText
            android:id="@+id/etEmail"
            android:layout_width="match_parent"
            android:layout_height="wrap_content"
            android:inputType="textEmailAddress"/>

    </com.google.android.material.textfield.TextInputLayout>

    <com.google.android.material.textfield.TextInputLayout
        android:id="@+id/tilPassword"
        style="@style/Widget.MaterialComponents.TextInputLayout.OutlinedBox"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        android:hint="Password"
        app:passwordToggleEnabled="true"
        app:layout_constraintTop_toBottomOf="@id/tilEmail"
        android:layout_marginTop="16dp">

        <com.google.android.material.textfield.TextInputEditText
            android:id="@+id/etPassword"
            android:layout_width="match_parent"
            android:layout_height="wrap_content"
            android:inputType="textPassword"/>

    </com.google.android.material.textfield.TextInputLayout>

    <com.google.android.material.button.MaterialButton
        android:id="@+id/btnLogin"
        android:layout_width="match_parent"
        android:layout_height="56dp"
        android:text="Login"
        app:layout_constraintTop_toBottomOf="@id/tilPassword"
        android:layout_marginTop="32dp"/>

    <ProgressBar
        android:id="@+id/progressBar"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:visibility="gone"
        app:layout_constraintTop_toTopOf="parent"
        app:layout_constraintBottom_toBottomOf="parent"
        app:layout_constraintStart_toStartOf="parent"
        app:layout_constraintEnd_toEndOf="parent"/>

</androidx.constraintlayout.widget.ConstraintLayout>
*/
