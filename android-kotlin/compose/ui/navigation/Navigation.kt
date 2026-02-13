package com.sams.app.ui.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.sams.app.ui.auth.LoginScreen
import com.sams.app.ui.auth.AuthViewModel
import com.sams.app.ui.student.StudentDashboardScreen
import com.sams.app.ui.student.StudentScheduleScreen
import com.sams.app.ui.student.MarkAttendanceScreen
import com.sams.app.ui.student.AttendanceHistoryScreen
import com.sams.app.ui.student.FaceRegistrationScreen
import com.sams.app.ui.student.StudentProfileScreen
import com.sams.app.ui.teacher.TeacherDashboardScreen
import com.sams.app.ui.teacher.TeacherScheduleScreen
import com.sams.app.ui.teacher.StartClassScreen
import com.sams.app.ui.teacher.ClassAttendanceScreen
import com.sams.app.ui.teacher.TeacherProfileScreen
import com.sams.app.ui.common.NotificationsScreen

sealed class Screen(val route: String) {
    // Auth
    object Login : Screen("login")
    
    // Student
    object StudentDashboard : Screen("student/dashboard")
    object StudentSchedule : Screen("student/schedule")
    object MarkAttendance : Screen("student/mark-attendance/{scheduleId}/{teacherLat}/{teacherLon}/{subjectName}") {
        fun createRoute(scheduleId: Int, teacherLat: Double, teacherLon: Double, subjectName: String) =
            "student/mark-attendance/$scheduleId/$teacherLat/$teacherLon/$subjectName"
    }
    object AttendanceHistory : Screen("student/attendance-history")
    object FaceRegistration : Screen("student/face-registration")
    object StudentProfile : Screen("student/profile")
    
    // Teacher
    object TeacherDashboard : Screen("teacher/dashboard")
    object TeacherSchedule : Screen("teacher/schedule")
    object StartClass : Screen("teacher/start-class/{scheduleId}") {
        fun createRoute(scheduleId: Int) = "teacher/start-class/$scheduleId"
    }
    object ClassAttendance : Screen("teacher/class-attendance/{scheduleId}") {
        fun createRoute(scheduleId: Int) = "teacher/class-attendance/$scheduleId"
    }
    object TeacherProfile : Screen("teacher/profile")
    
    // Common
    object Notifications : Screen("notifications")
}

@Composable
fun SAMSNavHost(
    navController: NavHostController = rememberNavController(),
    authViewModel: AuthViewModel = hiltViewModel()
) {
    val isLoggedIn by authViewModel.isLoggedIn.collectAsState()
    val userRole by authViewModel.userRole.collectAsState()
    
    val startDestination = when {
        !isLoggedIn -> Screen.Login.route
        userRole == "student" -> Screen.StudentDashboard.route
        userRole == "teacher" -> Screen.TeacherDashboard.route
        else -> Screen.Login.route
    }
    
    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        // Auth
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = { role ->
                    val destination = if (role == "student") 
                        Screen.StudentDashboard.route 
                    else 
                        Screen.TeacherDashboard.route
                    navController.navigate(destination) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                }
            )
        }
        
        // Student Screens
        composable(Screen.StudentDashboard.route) {
            StudentDashboardScreen(
                onNavigateToSchedule = { navController.navigate(Screen.StudentSchedule.route) },
                onNavigateToHistory = { navController.navigate(Screen.AttendanceHistory.route) },
                onNavigateToProfile = { navController.navigate(Screen.StudentProfile.route) },
                onNavigateToNotifications = { navController.navigate(Screen.Notifications.route) },
                onNavigateToFaceRegistration = { navController.navigate(Screen.FaceRegistration.route) },
                onMarkAttendance = { scheduleId, lat, lon, name ->
                    navController.navigate(Screen.MarkAttendance.createRoute(scheduleId, lat, lon, name))
                },
                onLogout = {
                    authViewModel.logout()
                    navController.navigate(Screen.Login.route) {
                        popUpTo(0) { inclusive = true }
                    }
                }
            )
        }
        
        composable(Screen.StudentSchedule.route) {
            StudentScheduleScreen(
                onBack = { navController.popBackStack() },
                onMarkAttendance = { scheduleId, lat, lon, name ->
                    navController.navigate(Screen.MarkAttendance.createRoute(scheduleId, lat, lon, name))
                }
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
            MarkAttendanceScreen(
                scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: 0,
                teacherLat = backStackEntry.arguments?.getFloat("teacherLat")?.toDouble() ?: 0.0,
                teacherLon = backStackEntry.arguments?.getFloat("teacherLon")?.toDouble() ?: 0.0,
                subjectName = backStackEntry.arguments?.getString("subjectName") ?: "",
                onBack = { navController.popBackStack() },
                onSuccess = { navController.popBackStack() }
            )
        }
        
        composable(Screen.AttendanceHistory.route) {
            AttendanceHistoryScreen(
                onBack = { navController.popBackStack() }
            )
        }
        
        composable(Screen.FaceRegistration.route) {
            FaceRegistrationScreen(
                onBack = { navController.popBackStack() },
                onSuccess = { navController.popBackStack() }
            )
        }
        
        composable(Screen.StudentProfile.route) {
            StudentProfileScreen(
                onBack = { navController.popBackStack() }
            )
        }
        
        // Teacher Screens
        composable(Screen.TeacherDashboard.route) {
            TeacherDashboardScreen(
                onNavigateToSchedule = { navController.navigate(Screen.TeacherSchedule.route) },
                onNavigateToProfile = { navController.navigate(Screen.TeacherProfile.route) },
                onNavigateToNotifications = { navController.navigate(Screen.Notifications.route) },
                onStartClass = { scheduleId ->
                    navController.navigate(Screen.StartClass.createRoute(scheduleId))
                },
                onViewAttendance = { scheduleId ->
                    navController.navigate(Screen.ClassAttendance.createRoute(scheduleId))
                },
                onLogout = {
                    authViewModel.logout()
                    navController.navigate(Screen.Login.route) {
                        popUpTo(0) { inclusive = true }
                    }
                }
            )
        }
        
        composable(Screen.TeacherSchedule.route) {
            TeacherScheduleScreen(
                onBack = { navController.popBackStack() },
                onStartClass = { scheduleId ->
                    navController.navigate(Screen.StartClass.createRoute(scheduleId))
                },
                onViewAttendance = { scheduleId ->
                    navController.navigate(Screen.ClassAttendance.createRoute(scheduleId))
                }
            )
        }
        
        composable(
            route = Screen.StartClass.route,
            arguments = listOf(navArgument("scheduleId") { type = NavType.IntType })
        ) { backStackEntry ->
            StartClassScreen(
                scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: 0,
                onBack = { navController.popBackStack() },
                onSessionStarted = { scheduleId ->
                    navController.navigate(Screen.ClassAttendance.createRoute(scheduleId)) {
                        popUpTo(Screen.StartClass.route) { inclusive = true }
                    }
                }
            )
        }
        
        composable(
            route = Screen.ClassAttendance.route,
            arguments = listOf(navArgument("scheduleId") { type = NavType.IntType })
        ) { backStackEntry ->
            ClassAttendanceScreen(
                scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: 0,
                onBack = { navController.popBackStack() }
            )
        }
        
        composable(Screen.TeacherProfile.route) {
            TeacherProfileScreen(
                onBack = { navController.popBackStack() }
            )
        }
        
        // Common
        composable(Screen.Notifications.route) {
            NotificationsScreen(
                onBack = { navController.popBackStack() }
            )
        }
    }
}
