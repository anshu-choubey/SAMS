package com.sams.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import com.sams.app.data.repository.SessionManager
import com.sams.app.ui.navigation.SAMSNavHost
import com.sams.app.ui.navigation.Screen
import com.sams.app.ui.theme.SAMSTheme
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    
    @Inject
    lateinit var sessionManager: SessionManager
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        
        val startDestination = getStartDestination()
        
        setContent {
            SAMSTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    SAMSNavHost(startDestination = startDestination)
                }
            }
        }
    }
    
    private fun getStartDestination(): String {
        if (!sessionManager.isLoggedIn()) {
            return Screen.Login.route
        }
        
        val user = sessionManager.getUser()
        return when (user?.role?.lowercase()) {
            "student" -> Screen.StudentDashboard.route
            "teacher" -> Screen.TeacherDashboard.route
            else -> Screen.Login.route
        }
    }
}
