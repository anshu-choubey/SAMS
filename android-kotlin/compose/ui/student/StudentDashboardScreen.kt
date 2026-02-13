package com.sams.app.ui.student

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.ScheduleItem
import com.sams.app.data.models.StudentDashboardData
import com.sams.app.ui.theme.PresentColor
import com.sams.app.ui.theme.AbsentColor
import com.sams.app.ui.theme.Warning

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StudentDashboardScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onNavigateToSchedule: () -> Unit,
    onNavigateToHistory: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToNotifications: () -> Unit,
    onNavigateToFaceRegistration: () -> Unit,
    onMarkAttendance: (Int, Double, Double, String) -> Unit,
    onLogout: () -> Unit
) {
    val uiState by viewModel.dashboardState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadDashboard()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Dashboard") },
                actions = {
                    IconButton(onClick = onNavigateToNotifications) {
                        Icon(Icons.Outlined.Notifications, contentDescription = "Notifications")
                    }
                    IconButton(onClick = onLogout) {
                        Icon(Icons.Default.Logout, contentDescription = "Logout")
                    }
                }
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is StudentUiState.Loading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
            }
            is StudentUiState.Success -> {
                DashboardContent(
                    data = state.data as StudentDashboardData,
                    modifier = Modifier.padding(padding),
                    onNavigateToSchedule = onNavigateToSchedule,
                    onNavigateToHistory = onNavigateToHistory,
                    onNavigateToProfile = onNavigateToProfile,
                    onNavigateToFaceRegistration = onNavigateToFaceRegistration,
                    onMarkAttendance = onMarkAttendance
                )
            }
            is StudentUiState.Error -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(state.message, color = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.loadDashboard() }) {
                            Text("Retry")
                        }
                    }
                }
            }
            else -> {}
        }
    }
}

@Composable
private fun DashboardContent(
    data: StudentDashboardData,
    modifier: Modifier = Modifier,
    onNavigateToSchedule: () -> Unit,
    onNavigateToHistory: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToFaceRegistration: () -> Unit,
    onMarkAttendance: (Int, Double, Double, String) -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Profile Card
        item {
            ProfileCard(
                name = data.profile.fullName ?: "Student",
                rollNumber = data.profile.rollNumber ?: "",
                department = data.profile.departmentName ?: data.profile.department ?: "N/A",
                semester = data.profile.semester ?: 0,
                faceRegistered = data.profile.faceRegistered,
                onProfileClick = onNavigateToProfile,
                onRegisterFace = onNavigateToFaceRegistration
            )
        }
        
        // Face Registration Warning
        if (!data.profile.faceRegistered) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Warning.copy(alpha = 0.1f))
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Default.Warning,
                            contentDescription = null,
                            tint = Warning
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                "Face Not Registered",
                                style = MaterialTheme.typography.titleSmall,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                "Register your face to mark attendance",
                                style = MaterialTheme.typography.bodySmall
                            )
                        }
                        TextButton(onClick = onNavigateToFaceRegistration) {
                            Text("Register")
                        }
                    }
                }
            }
        }
        
        // Attendance Stats
        item {
            val attendance = data.getAttendance()
            AttendanceStatsCard(
                totalClasses = attendance.totalClasses,
                attended = attendance.attended,
                percentage = attendance.percentage,
                onViewHistory = onNavigateToHistory
            )
        }
        
        // Quick Actions
        item {
            QuickActionsRow(
                onSchedule = onNavigateToSchedule,
                onHistory = onNavigateToHistory,
                onProfile = onNavigateToProfile
            )
        }
        
        // Active Session
        data.activeSession?.let { session ->
            item {
                ActiveSessionCard(
                    subjectName = session.subjectName,
                    teacherName = session.teacherName,
                    classroom = session.classroom,
                    onMarkAttendance = {
                        onMarkAttendance(
                            session.scheduleId,
                            session.teacherLatitude,
                            session.teacherLongitude,
                            session.subjectName
                        )
                    }
                )
            }
        }
        
        // Today's Schedule
        item {
            Text(
                "Today's Schedule",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
        }
        
        if (data.todaySchedule.isEmpty()) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(32.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text("No classes scheduled for today")
                    }
                }
            }
        } else {
            items(data.todaySchedule) { schedule ->
                ScheduleCard(
                    schedule = schedule,
                    onMarkAttendance = { onMarkAttendance(schedule.scheduleId, 0.0, 0.0, schedule.subjectName) }
                )
            }
        }
    }
}

@Composable
private fun ProfileCard(
    name: String,
    rollNumber: String,
    department: String,
    semester: Int,
    faceRegistered: Boolean,
    onProfileClick: () -> Unit,
    onRegisterFace: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onProfileClick),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer
        )
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .clip(CircleShape)
                    .background(MaterialTheme.colorScheme.primary),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = name.firstOrNull()?.uppercase() ?: "S",
                    style = MaterialTheme.typography.headlineMedium,
                    color = MaterialTheme.colorScheme.onPrimary
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = name,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = rollNumber,
                    style = MaterialTheme.typography.bodyMedium
                )
                Text(
                    text = "$department • Sem $semester",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            if (faceRegistered) {
                Icon(
                    Icons.Default.VerifiedUser,
                    contentDescription = "Face Registered",
                    tint = PresentColor
                )
            }
        }
    }
}

@Composable
private fun AttendanceStatsCard(
    totalClasses: Int,
    attended: Int,
    percentage: Double,
    onViewHistory: () -> Unit
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    "Attendance",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                TextButton(onClick = onViewHistory) {
                    Text("View History")
                    Icon(
                        Icons.Default.ChevronRight,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                StatItem(
                    value = "$attended",
                    label = "Attended",
                    color = PresentColor
                )
                StatItem(
                    value = "${totalClasses - attended}",
                    label = "Missed",
                    color = AbsentColor
                )
                StatItem(
                    value = "${percentage.toInt()}%",
                    label = "Percentage",
                    color = if (percentage >= 75) PresentColor else AbsentColor
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            LinearProgressIndicator(
                progress = { (percentage / 100).toFloat() },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(8.dp)
                    .clip(RoundedCornerShape(4.dp)),
                color = if (percentage >= 75) PresentColor else AbsentColor,
            )
        }
    }
}

@Composable
private fun StatItem(
    value: String,
    label: String,
    color: androidx.compose.ui.graphics.Color
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            text = value,
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            color = color
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
private fun QuickActionsRow(
    onSchedule: () -> Unit,
    onHistory: () -> Unit,
    onProfile: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        QuickActionCard(
            icon = Icons.Outlined.CalendarMonth,
            label = "Schedule",
            onClick = onSchedule,
            modifier = Modifier.weight(1f)
        )
        QuickActionCard(
            icon = Icons.Outlined.History,
            label = "History",
            onClick = onHistory,
            modifier = Modifier.weight(1f)
        )
        QuickActionCard(
            icon = Icons.Outlined.Person,
            label = "Profile",
            onClick = onProfile,
            modifier = Modifier.weight(1f)
        )
    }
}

@Composable
private fun QuickActionCard(
    icon: ImageVector,
    label: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.clickable(onClick = onClick)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                icon,
                contentDescription = label,
                modifier = Modifier.size(32.dp),
                tint = MaterialTheme.colorScheme.primary
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(label, style = MaterialTheme.typography.labelMedium)
        }
    }
}

@Composable
private fun ActiveSessionCard(
    subjectName: String,
    teacherName: String,
    classroom: String?,
    onMarkAttendance: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = PresentColor.copy(alpha = 0.1f)
        )
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(12.dp)
                        .clip(CircleShape)
                        .background(PresentColor)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    "Active Session",
                    style = MaterialTheme.typography.labelMedium,
                    color = PresentColor,
                    fontWeight = FontWeight.Bold
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                subjectName,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
            Text(
                "by $teacherName",
                style = MaterialTheme.typography.bodyMedium
            )
            classroom?.let {
                Text(
                    "Room: $it",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Button(
                onClick = onMarkAttendance,
                modifier = Modifier.fillMaxWidth()
            ) {
                Icon(Icons.Default.CheckCircle, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Mark Attendance")
            }
        }
    }
}

@Composable
private fun ScheduleCard(
    schedule: ScheduleItem,
    onMarkAttendance: () -> Unit
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.width(60.dp)
            ) {
                Text(
                    schedule.startTime.take(5),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    schedule.endTime.take(5),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    schedule.subjectName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    schedule.teacherName,
                    style = MaterialTheme.typography.bodySmall
                )
                schedule.classroom?.let {
                    Text(
                        "Room: $it",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            if (schedule.attendanceMarked) {
                Icon(
                    Icons.Default.CheckCircle,
                    contentDescription = "Attended",
                    tint = PresentColor
                )
            } else if (schedule.isActive) {
                FilledTonalButton(onClick = onMarkAttendance) {
                    Text("Mark")
                }
            }
        }
    }
}
