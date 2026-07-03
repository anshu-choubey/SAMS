package com.sams.app.ui.navigation

import android.net.Uri
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.sams.app.ui.auth.AuthViewModel
import com.sams.app.ui.auth.LoginScreen
import com.sams.app.ui.common.NotificationsScreen
import com.sams.app.ui.student.*
import com.sams.app.ui.teacher.*

sealed class Screen(val route: String) {
    // Auth
    object Login : Screen("login")

    // Student
    object StudentDashboard : Screen("student/dashboard")
    object StudentSchedule : Screen("student/schedule")
    object MarkAttendance : Screen("student/mark-attendance/{scheduleId}/{teacherLat}/{teacherLon}/{subjectName}") {
        fun createRoute(scheduleId: Int, teacherLat: Double, teacherLon: Double, subjectName: String) =
            // ✅ Uri.encode prevents crashes from spaces/special chars in subject name
            "student/mark-attendance/$scheduleId/$teacherLat/$teacherLon/${Uri.encode(subjectName)}"
    }
    object AttendanceHistory : Screen("student/attendance-history")
    object FaceRegistration : Screen("student/face-registration")
    object StudentProfile : Screen("student/profile")
    object NotificationPreferences : Screen("student/notification-preferences")
    object ContinuousAttendance : Screen("student/continuous-attendance/{sessionId}") {
        fun createRoute(sessionId: Int) = "student/continuous-attendance/$sessionId"
    }
    object ActiveChecks : Screen("student/active-checks")
    object RespondToCheck : Screen("student/respond-to-check/{checkPointId}/{teacherLat}/{teacherLon}/{subjectName}") {
        fun createRoute(checkPointId: Int, teacherLat: Double, teacherLon: Double, subjectName: String) =
            "student/respond-to-check/$checkPointId/$teacherLat/$teacherLon/${Uri.encode(subjectName)}"
    }

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
    val isReady by authViewModel.isReady.collectAsState()
    val isLoggedIn by authViewModel.isLoggedIn.collectAsState()
    val userRole by authViewModel.userRole.collectAsState()

    // ✅ Block NavHost until DataStore session is loaded — fixes login flash
    if (!isReady) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        return
    }

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
            val studentViewModel: StudentViewModel = hiltViewModel()
            StudentDashboardScreen(
                viewModel = studentViewModel,
                onNavigateToSchedule = { navController.navigate(Screen.StudentSchedule.route) },
                onNavigateToHistory = { navController.navigate(Screen.AttendanceHistory.route) },
                onNavigateToProfile = { navController.navigate(Screen.StudentProfile.route) },
                onNavigateToNotifications = { navController.navigate(Screen.Notifications.route) },
                onNavigateToFaceRegistration = { navController.navigate(Screen.FaceRegistration.route) },
                onMarkAttendance = { scheduleId, lat, lon, name ->
                    navController.navigate(Screen.MarkAttendance.createRoute(scheduleId, lat, lon, name))
                },
                onNavigateToContinuousAttendance = { sessionId ->
                    navController.navigate(Screen.ContinuousAttendance.createRoute(sessionId))
                },
                onNavigateToActiveChecks = {
                    if (studentViewModel.isMultiCheckEnabled()) {
                        navController.navigate(Screen.ActiveChecks.route)
                    }
                },
                onLogout = {
                    authViewModel.logout {
                        navController.navigate(Screen.Login.route) {
                            popUpTo(navController.graph.id) { inclusive = true }
                            launchSingleTop = true
                        }
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
                navArgument("teacherLat") { type = NavType.StringType },
                navArgument("teacherLon") { type = NavType.StringType },
                navArgument("subjectName") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            val studentViewModel: StudentViewModel = hiltViewModel()
            MarkAttendanceScreen(
                scheduleId = backStackEntry.arguments?.getInt("scheduleId") ?: 0,
                teacherLat = backStackEntry.arguments?.getString("teacherLat")?.toDoubleOrNull() ?: 0.0,
                teacherLon = backStackEntry.arguments?.getString("teacherLon")?.toDoubleOrNull() ?: 0.0,
                subjectName = backStackEntry.arguments?.getString("subjectName") ?: "",
                onBack = { navController.popBackStack() },
                onSuccess = { 
                    if (studentViewModel.isMultiCheckEnabled()) {
                        navController.navigate(Screen.ActiveChecks.route) {
                            popUpTo(Screen.StudentDashboard.route) { inclusive = false }
                        }
                    } else {
                        navController.navigate(Screen.StudentDashboard.route) {
                            popUpTo(Screen.StudentDashboard.route) { inclusive = true }
                        }
                    }
                }
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

        composable(Screen.NotificationPreferences.route) {
            NotificationPreferencesScreen(
                onBack = { navController.popBackStack() }
            )
        }

        composable(
            route = Screen.ContinuousAttendance.route,
            arguments = listOf(navArgument("sessionId") { type = NavType.IntType })
        ) { backStackEntry ->
            ContinuousAttendanceScreen(
                sessionId = backStackEntry.arguments?.getInt("sessionId") ?: 0,
                onNavigateBack = { navController.popBackStack() },
                onSessionComplete = {
                    // Navigate back to dashboard when session completes
                    navController.navigate(Screen.StudentDashboard.route) {
                        popUpTo(Screen.ContinuousAttendance.route) { inclusive = true }
                    }
                }
            )
        }

        composable(Screen.ActiveChecks.route) {
            ActiveChecksScreen(
                onNavigateBack = {
                    navController.navigate(Screen.StudentDashboard.route) {
                        popUpTo(Screen.StudentDashboard.route) { inclusive = true }
                    }
                },
                onNavigateToRespondToCheck = { checkPointId, teacherLat, teacherLon, subjectName ->
                    navController.navigate(Screen.RespondToCheck.createRoute(checkPointId, teacherLat, teacherLon, subjectName))
                }
            )
        }
        
        composable(
            route = Screen.RespondToCheck.route,
            arguments = listOf(
                navArgument("checkPointId") { type = NavType.IntType },
                navArgument("teacherLat") { type = NavType.StringType },
                navArgument("teacherLon") { type = NavType.StringType },
                navArgument("subjectName") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            RespondToCheckScreen(
                checkPointId = backStackEntry.arguments?.getInt("checkPointId") ?: 0,
                teacherLat = backStackEntry.arguments?.getString("teacherLat")?.toDoubleOrNull() ?: 0.0,
                teacherLon = backStackEntry.arguments?.getString("teacherLon")?.toDoubleOrNull() ?: 0.0,
                subjectName = backStackEntry.arguments?.getString("subjectName") ?: "",
                onBack = { navController.popBackStack() },
                onSuccess = {
                    // After responding, navigate back to ActiveChecks to see remaining checks
                    navController.navigate(Screen.ActiveChecks.route) {
                        popUpTo(Screen.RespondToCheck.route) { inclusive = true }
                    }
                }
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
                    authViewModel.logout {
                        navController.navigate(Screen.Login.route) {
                            popUpTo(navController.graph.id) { inclusive = true }
                            launchSingleTop = true
                        }
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

        composable(Screen.Notifications.route) {
            NotificationsScreen(
                onBack = { navController.popBackStack() }
            )
        }
    }
}
