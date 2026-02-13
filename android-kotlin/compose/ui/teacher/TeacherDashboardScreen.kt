package com.sams.app.ui.teacher

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
import com.sams.app.data.models.TeacherDashboardData
import com.sams.app.data.models.TeacherScheduleItem
import com.sams.app.ui.theme.PresentColor
import com.sams.app.ui.theme.AbsentColor

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TeacherDashboardScreen(
    viewModel: TeacherViewModel = hiltViewModel(),
    onNavigateToSchedule: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToNotifications: () -> Unit,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit,
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
            is TeacherUiState.Loading -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            is TeacherUiState.Success -> {
                val data = state.data as TeacherDashboardData
                TeacherDashboardContent(
                    data = data,
                    modifier = Modifier.padding(padding),
                    onNavigateToSchedule = onNavigateToSchedule,
                    onNavigateToProfile = onNavigateToProfile,
                    onStartClass = onStartClass,
                    onViewAttendance = onViewAttendance,
                    onEndSession = { viewModel.endSession(it) }
                )
            }
            is TeacherUiState.Error -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
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
private fun TeacherDashboardContent(
    data: TeacherDashboardData,
    modifier: Modifier = Modifier,
    onNavigateToSchedule: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit,
    onEndSession: (Int?) -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Profile Card
        item {
            TeacherProfileCard(
                name = data.profile.fullName,
                email = data.profile.email,
                department = data.profile.departmentName ?: "N/A",
                subjectsCount = data.subjects.size,
                onProfileClick = onNavigateToProfile
            )
        }
        
        // Stats Row
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                StatsCard(
                    title = "Today's Classes",
                    value = "${data.todaySchedule.size}",
                    icon = Icons.Outlined.CalendarToday,
                    modifier = Modifier.weight(1f)
                )
                StatsCard(
                    title = "Total Students",
                    value = "${data.totalStudents}",
                    icon = Icons.Outlined.Groups,
                    modifier = Modifier.weight(1f)
                )
            }
        }
        
        // Active Session
        data.activeSession?.let { session ->
            item {
                ActiveSessionCard(
                    subjectName = session.subjectName,
                    classroom = session.classroom,
                    startTime = session.sessionStartTime,
                    presentCount = session.presentCount,
                    totalStudents = session.totalStudents,
                    onViewAttendance = { onViewAttendance(session.scheduleId) },
                    onEndSession = { onEndSession(session.sessionId) }
                )
            }
        }
        
        // Quick Actions
        item {
            QuickActionsRow(
                onSchedule = onNavigateToSchedule,
                onProfile = onNavigateToProfile
            )
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
                TeacherScheduleCard(
                    schedule = schedule,
                    onStartClass = { onStartClass(schedule.scheduleId) },
                    onViewAttendance = { onViewAttendance(schedule.scheduleId) }
                )
            }
        }
    }
}

@Composable
private fun TeacherProfileCard(
    name: String,
    email: String,
    department: String,
    subjectsCount: Int,
    onProfileClick: () -> Unit
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
                    text = name.firstOrNull()?.uppercase() ?: "T",
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
                    text = email,
                    style = MaterialTheme.typography.bodyMedium
                )
                Text(
                    text = "$department • $subjectsCount subjects",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Icon(
                Icons.Default.ChevronRight,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun StatsCard(
    title: String,
    value: String,
    icon: ImageVector,
    modifier: Modifier = Modifier
) {
    Card(modifier = modifier) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(32.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                value,
                style = MaterialTheme.typography.headlineMedium,
                fontWeight = FontWeight.Bold
            )
            Text(
                title,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun ActiveSessionCard(
    subjectName: String,
    classroom: String?,
    startTime: String?,
    presentCount: Int,
    totalStudents: Int,
    onViewAttendance: () -> Unit,
    onEndSession: () -> Unit
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
            classroom?.let {
                Text(
                    "Room: $it",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            startTime?.let {
                Text(
                    "Started at: $it",
                    style = MaterialTheme.typography.bodySmall
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Attendance Progress
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    "Attendance: $presentCount / $totalStudents",
                    style = MaterialTheme.typography.bodyMedium
                )
                Text(
                    "${if (totalStudents > 0) (presentCount * 100 / totalStudents) else 0}%",
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Bold
                )
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            LinearProgressIndicator(
                progress = { if (totalStudents > 0) presentCount.toFloat() / totalStudents else 0f },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(8.dp)
                    .clip(RoundedCornerShape(4.dp)),
                color = PresentColor,
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                OutlinedButton(
                    onClick = onViewAttendance,
                    modifier = Modifier.weight(1f)
                ) {
                    Icon(Icons.Default.List, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("View")
                }
                Button(
                    onClick = onEndSession,
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = AbsentColor
                    )
                ) {
                    Icon(Icons.Default.Stop, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("End")
                }
            }
        }
    }
}

@Composable
private fun QuickActionsRow(
    onSchedule: () -> Unit,
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
private fun TeacherScheduleCard(
    schedule: TeacherScheduleItem,
    onStartClass: () -> Unit,
    onViewAttendance: () -> Unit
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
                    schedule.subjectCode,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.primary
                )
                schedule.classroom?.let {
                    Text(
                        "Room: $it",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            when {
                schedule.sessionActive -> {
                    FilledTonalButton(onClick = onViewAttendance) {
                        Text("View")
                    }
                }
                schedule.isStartable -> {
                    Button(onClick = onStartClass) {
                        Text("Start")
                    }
                }
                schedule.isCompleted -> {
                    Icon(
                        Icons.Default.CheckCircle,
                        contentDescription = "Completed",
                        tint = PresentColor
                    )
                }
                else -> {
                    Text(
                        "Upcoming",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }
    }
}
