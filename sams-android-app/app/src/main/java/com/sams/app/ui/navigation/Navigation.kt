package com.sams.app.ui.navigation

import androidx.compose.runtime.*
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.sams.app.ui.auth.AuthUiState
import com.sams.app.ui.auth.AuthViewModel
import com.sams.app.ui.auth.LoginScreen
import com.sams.app.ui.notifications.NotificationsScreen
import com.sams.app.ui.student.*
import com.sams.app.ui.teacher.*

sealed class Screen(val route: String) {
    object Login : Screen("login")
    
    // Student screens
    object StudentDashboard : Screen("student/dashboard")
    object StudentSchedule : Screen("student/schedule")
    object AttendanceHistory : Screen("student/attendance-history")
    object StudentProfile : Screen("student/profile")
    object FaceRegistration : Screen("student/face-registration")
    object MarkAttendance : Screen("student/mark-attendance/{scheduleId}/{teacherLat}/{teacherLon}/{subjectName}") {
        fun createRoute(scheduleId: Int, teacherLat: Double, teacherLon: Double, subjectName: String) = 
            "student/mark-attendance/$scheduleId/$teacherLat/$teacherLon/${java.net.URLEncoder.encode(subjectName, "UTF-8")}"
    }
    
    // Teacher screens
    object TeacherDashboard : Screen("teacher/dashboard")
    object TeacherSchedule : Screen("teacher/schedule")
    object TeacherProfile : Screen("teacher/profile")
    object StartClass : Screen("teacher/start-class/{scheduleId}") {
        fun createRoute(scheduleId: Int) = "teacher/start-class/$scheduleId"
    }
    object ClassAttendance : Screen("teacher/class-attendance/{scheduleId}") {
        fun createRoute(scheduleId: Int) = "teacher/class-attendance/$scheduleId"
    }
    
    // Common
    object Notifications : Screen("notifications")
}

@Composable
fun SAMSNavHost(
    navController: NavHostController = rememberNavController(),
    authViewModel: AuthViewModel = hiltViewModel(),
    startDestination: String = Screen.Login.route
) {
    val authState by authViewModel.uiState.collectAsState()
    
    // Handle logout state - navigate to login when LoggedOut
    LaunchedEffect(authState) {
        if (authState is AuthUiState.LoggedOut) {
            navController.navigate(Screen.Login.route) {
                popUpTo(0) { inclusive = true }
            }
            authViewModel.resetState()
        }
    }

    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        // Login
        composable(Screen.Login.route) {
            LoginScreen(
                viewModel = authViewModel,
                onLoginSuccess = { role ->
                    val route = when (role.lowercase()) {
                        "student" -> Screen.StudentDashboard.route
                        "teacher" -> Screen.TeacherDashboard.route
                        else -> Screen.Login.route
                    }
                    navController.navigate(route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                }
            )
        }
        
        // ================ Student Screens ================
        
        composable(Screen.StudentDashboard.route) {
            StudentDashboardScreen(
                onNavigateToSchedule = {
                    navController.navigate(Screen.StudentSchedule.route)
                },
                onNavigateToHistory = {
                    navController.navigate(Screen.AttendanceHistory.route)
                },
                onNavigateToProfile = {
                    navController.navigate(Screen.StudentProfile.route)
                },
                onNavigateToNotifications = {
                    navController.navigate(Screen.Notifications.route)
                },
                onMarkAttendance = { scheduleId, lat, lon, subjectName ->
                    navController.navigate(Screen.MarkAttendance.createRoute(scheduleId, lat, lon, subjectName))
                },
                onNavigateToFaceRegistration = {
                    navController.navigate(Screen.FaceRegistration.route)
                },
                onLogout = {
                    authViewModel.logout()
                }
            )
        }
        
        composable(Screen.StudentSchedule.route) {
            StudentScheduleScreen(
                onBack = { navController.popBackStack() },
                onMarkAttendance = { scheduleId, lat, lon, subjectName ->
                    navController.navigate(Screen.MarkAttendance.createRoute(scheduleId, lat, lon, subjectName))
                }
            )
        }
        
        composable(Screen.AttendanceHistory.route) {
            AttendanceHistoryScreen(
                onBack = { navController.popBackStack() }
            )
        }
        
        composable(Screen.StudentProfile.route) {
            StudentProfileScreen(
                onBack = { navController.popBackStack() }
            )
        }
        
        composable(Screen.FaceRegistration.route) {
            FaceRegistrationScreen(
                onBack = { navController.popBackStack() },
                onSuccess = { navController.popBackStack() }
            )
        }
        
        composable(
            route = Screen.MarkAttendance.route,
            arguments = listOf(
                navArgument("scheduleId") { type = NavType.IntType },
                navArgument("teacherLat") { type = NavType.FloatType },
                navArgument("teacherLon") { type = NavType.FloatType },
                navArgument("subjectName") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            val scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: return@composable
            val teacherLat = backStackEntry.arguments?.getFloat("teacherLat")?.toDouble() ?: 0.0
            val teacherLon = backStackEntry.arguments?.getFloat("teacherLon")?.toDouble() ?: 0.0
            val subjectName = java.net.URLDecoder.decode(
                backStackEntry.arguments?.getString("subjectName") ?: "", "UTF-8"
            )
            MarkAttendanceScreen(
                scheduleId = scheduleId,
                teacherLat = teacherLat,
                teacherLon = teacherLon,
                subjectName = subjectName,
                onBack = { navController.popBackStack() },
                onSuccess = {
                    navController.popBackStack(Screen.StudentDashboard.route, inclusive = false)
                }
            )
        }
        
        // ================ Teacher Screens ================
        
        composable(Screen.TeacherDashboard.route) { backStackEntry ->
            // Observe refresh signal from ClassAttendance
            val shouldRefresh = backStackEntry.savedStateHandle.get<Boolean>("refresh") ?: false
            
            TeacherDashboardScreen(
                shouldRefresh = shouldRefresh,
                onRefreshHandled = { backStackEntry.savedStateHandle.remove<Boolean>("refresh") },
                onNavigateToSchedule = {
                    navController.navigate(Screen.TeacherSchedule.route)
                },
                onNavigateToProfile = {
                    navController.navigate(Screen.TeacherProfile.route)
                },
                onNavigateToNotifications = {
                    navController.navigate(Screen.Notifications.route)
                },
                onStartClass = { scheduleId ->
                    navController.navigate(Screen.StartClass.createRoute(scheduleId))
                },
                onViewAttendance = { scheduleId ->
                    navController.navigate(Screen.ClassAttendance.createRoute(scheduleId))
                },
                onLogout = {
                    authViewModel.logout()
                }
            )
        }
        
        composable(Screen.TeacherSchedule.route) {
            TeacherScheduleScreen(
                onNavigateBack = { navController.popBackStack() },
                onStartClass = { scheduleId ->
                    navController.navigate(Screen.StartClass.createRoute(scheduleId))
                },
                onViewAttendance = { scheduleId ->
                    navController.navigate(Screen.ClassAttendance.createRoute(scheduleId))
                }
            )
        }
        
        composable(Screen.TeacherProfile.route) {
            TeacherProfileScreen(
                onNavigateBack = { navController.popBackStack() },
                onLogout = {
                    authViewModel.logout()
                }
            )
        }
        
        composable(
            route = Screen.StartClass.route,
            arguments = listOf(navArgument("scheduleId") { type = NavType.IntType })
        ) { backStackEntry ->
            val scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: return@composable
            StartClassScreen(
                scheduleId = scheduleId,
                onNavigateBack = { navController.popBackStack() },
                onViewAttendance = {
                    navController.navigate(Screen.ClassAttendance.createRoute(scheduleId)) {
                        popUpTo(Screen.TeacherDashboard.route)
                    }
                }
            )
        }
        
        composable(
            route = Screen.ClassAttendance.route,
            arguments = listOf(navArgument("scheduleId") { type = NavType.IntType })
        ) { backStackEntry ->
            val scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: return@composable
            ClassAttendanceScreen(
                scheduleId = scheduleId,
                onNavigateBack = { navController.popBackStack() },
                onEndClass = {
                    // Signal dashboard to refresh
                    navController.previousBackStackEntry?.savedStateHandle?.set("refresh", true)
                    navController.popBackStack(Screen.TeacherDashboard.route, inclusive = false)
                }
            )
        }
        
        // ================ Common Screens ================
        
        composable(Screen.Notifications.route) {
            NotificationsScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }
    }
}
