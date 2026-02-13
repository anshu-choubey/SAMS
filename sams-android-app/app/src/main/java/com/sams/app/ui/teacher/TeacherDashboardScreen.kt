package com.sams.app.ui.teacher

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TeacherDashboardScreen(
    viewModel: TeacherViewModel = hiltViewModel(),
    shouldRefresh: Boolean = false,
    onRefreshHandled: () -> Unit = {},
    onNavigateToSchedule: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToNotifications: () -> Unit,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit,
    onLogout: () -> Unit
) {
    val uiState by viewModel.dashboardState.collectAsState()
    val isRefreshing by viewModel.isRefreshing.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadDashboard()
    }
    
    // Handle refresh signal from ClassAttendance screen
    LaunchedEffect(shouldRefresh) {
        if (shouldRefresh) {
            viewModel.refreshDashboard()
            onRefreshHandled()
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { 
                    Text(
                        "Dashboard",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                },
                actions = {
                    IconButton(onClick = { viewModel.refreshDashboard() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                    IconButton(onClick = onNavigateToNotifications) {
                        Icon(Icons.Outlined.Notifications, contentDescription = "Notifications")
                    }
                    IconButton(onClick = onLogout) {
                        Icon(Icons.Default.Logout, contentDescription = "Logout")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
                )
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is TeacherUiState.Loading -> LoadingScreen()
            is TeacherUiState.Error -> ErrorScreen(
                message = state.message,
                onRetry = { viewModel.loadDashboard() }
            )
            is TeacherUiState.Success -> {
                val data = state.data as TeacherDashboardData
                PullToRefreshBox(
                    isRefreshing = isRefreshing,
                    onRefresh = { viewModel.refreshDashboard() },
                    modifier = Modifier.padding(padding)
                ) {
                    TeacherDashboardContent(
                        data = data,
                        onNavigateToSchedule = onNavigateToSchedule,
                        onNavigateToProfile = onNavigateToProfile,
                        onStartClass = onStartClass,
                        onViewAttendance = onViewAttendance,
                        onEndSession = { viewModel.endSession() }
                    )
                }
            }
            else -> {}
        }
    }
}

@Composable
private fun TeacherDashboardContent(
    data: TeacherDashboardData,
    onNavigateToSchedule: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit,
    onEndSession: () -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Welcome Card
        item {
            TeacherWelcomeCard(
                profile = data.profile,
                onProfileClick = onNavigateToProfile
            )
        }
        
        // Active Session Card
        data.activeSession?.let { session ->
            item {
                ActiveSessionCard(
                    session = session,
                    onViewAttendance = { onViewAttendance(session.scheduleId) },
                    onEndSession = onEndSession
                )
            }
        }
        
        // Stats Row
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                StatCard(
                    title = "Total Students",
                    value = data.totalStudents.toString(),
                    icon = Icons.Outlined.People,
                    iconTint = PrimaryBlue,
                    containerColor = PrimaryBlueContainer.copy(alpha = 0.5f),
                    modifier = Modifier.weight(1f)
                )
                StatCard(
                    title = "Subjects",
                    value = data.subjects.size.toString(),
                    icon = Icons.Outlined.MenuBook,
                    iconTint = SecondaryTeal,
                    containerColor = SecondaryTealContainer.copy(alpha = 0.5f),
                    modifier = Modifier.weight(1f)
                )
            }
        }
        
        // Quick Actions
        item {
            SectionHeader(title = "Quick Actions")
            QuickActionsRow(
                onSchedule = onNavigateToSchedule,
                onProfile = onNavigateToProfile
            )
        }
        
        // My Subjects
        if (data.subjects.isNotEmpty()) {
            item {
                SectionHeader(title = "My Subjects")
            }
            item {
                SubjectsRow(subjects = data.subjects)
            }
        }
        
        // Today's Schedule
        item {
            SectionHeader(
                title = "Today's Classes",
                actionText = "Full Schedule",
                onAction = onNavigateToSchedule
            )
        }
        
        if (data.todaySchedule.isEmpty()) {
            item {
                EmptyScheduleCard()
            }
        } else {
            items(data.todaySchedule) { schedule ->
                TeacherScheduleCard(
                    schedule = schedule,
                    hasActiveSession = data.activeSession != null,
                    onStartClass = { onStartClass(schedule.scheduleId) },
                    onViewAttendance = { onViewAttendance(schedule.scheduleId) }
                )
            }
        }
        
        item { Spacer(modifier = Modifier.height(16.dp)) }
    }
}

@Composable
private fun TeacherWelcomeCard(
    profile: TeacherProfile,
    onProfileClick: () -> Unit
) {
    GradientCard(
        modifier = Modifier.fillMaxWidth(),
        gradientColors = listOf(SecondaryTeal, PrimaryBlue),
        onClick = onProfileClick
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = profile.fullName.firstOrNull()?.uppercase() ?: "T",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Welcome back,",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f)
                )
                Text(
                    text = profile.fullName,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                profile.designation?.let {
                    Text(
                        text = it,
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.8f)
                    )
                }
            }
            
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = Color.White.copy(alpha = 0.6f)
            )
        }
    }
}

@Composable
private fun ActiveSessionCard(
    session: TeacherActiveSession,
    onViewAttendance: () -> Unit,
    onEndSession: () -> Unit
) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.horizontalGradient(
                        colors = listOf(
                            SuccessGreenContainer,
                            SuccessGreenContainer.copy(alpha = 0.5f)
                        )
                    )
                )
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(12.dp)
                            .clip(CircleShape)
                            .background(SuccessGreen)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "CLASS IN PROGRESS",
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = FontWeight.Bold,
                        color = SuccessGreen
                    )
                }
                session.sessionStartTime?.let {
                    Text(
                        text = "Started: $it",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = session.subjectName,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold
            )
            Text(
                text = session.subjectCode,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Attendance Stats
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = session.presentCount.toString(),
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = SuccessGreen
                    )
                    Text(
                        text = "Present",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                Box(
                    modifier = Modifier
                        .width(1.dp)
                        .height(40.dp)
                        .background(Divider)
                )
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = (session.totalStudents - session.presentCount).toString(),
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = ErrorRed
                    )
                    Text(
                        text = "Absent",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                Box(
                    modifier = Modifier
                        .width(1.dp)
                        .height(40.dp)
                        .background(Divider)
                )
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = session.totalStudents.toString(),
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "Total",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                OutlinedButton(
                    onClick = onViewAttendance,
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Icon(Icons.Outlined.People, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("View")
                }
                Button(
                    onClick = onEndSession,
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(containerColor = ErrorRed),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Icon(Icons.Default.Stop, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("End Class")
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
        horizontalArrangement = Arrangement.SpaceEvenly
    ) {
        QuickActionButton(
            icon = Icons.Outlined.CalendarMonth,
            label = "Schedule",
            onClick = onSchedule,
            containerColor = PrimaryBlueContainer,
            contentColor = PrimaryBlue
        )
        QuickActionButton(
            icon = Icons.Outlined.Person,
            label = "Profile",
            onClick = onProfile,
            containerColor = AccentPurpleContainer,
            contentColor = AccentPurple
        )
    }
}

@Composable
private fun SubjectsRow(subjects: List<TeacherSubject>) {
    LazyRow(
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(subjects) { subject ->
            SubjectCard(subject = subject)
        }
    }
}

@Composable
private fun SubjectCard(subject: TeacherSubject) {
    SAMSCard(modifier = Modifier.width(160.dp)) {
        Column(modifier = Modifier.padding(16.dp)) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(PrimaryBlueContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.MenuBook,
                    contentDescription = null,
                    tint = PrimaryBlue,
                    modifier = Modifier.size(20.dp)
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = subject.subjectCode,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.primary,
                fontWeight = FontWeight.SemiBold
            )
            Text(
                text = subject.subjectName,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.Medium,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Sem ${subject.semester} • ${subject.section}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun TeacherScheduleCard(
    schedule: TeacherScheduleItem,
    hasActiveSession: Boolean,
    onStartClass: () -> Unit,
    onViewAttendance: () -> Unit
) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Time Column
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.width(60.dp)
            ) {
                Text(
                    text = schedule.startTime.take(5),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Text(
                    text = schedule.endTime.take(5),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            // Divider
            Box(
                modifier = Modifier
                    .width(3.dp)
                    .height(50.dp)
                    .clip(RoundedCornerShape(1.5.dp))
                    .background(
                        when {
                            schedule.sessionActive -> SuccessGreen
                            schedule.isCompleted -> MaterialTheme.colorScheme.primary
                            else -> MaterialTheme.colorScheme.outline
                        }
                    )
            )
            
            Spacer(modifier = Modifier.width(12.dp))
            
            // Subject Info
            Column(modifier = Modifier.weight(1f)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = schedule.subjectName,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f, fill = false)
                    )
                    when {
                        schedule.sessionActive -> StatusBadge(status = "Ongoing")
                        schedule.isCompleted -> StatusBadge(status = "Completed")
                    }
                }
                Text(
                    text = schedule.subjectCode,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = "Semester ${schedule.semester} • ${schedule.section}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                schedule.classroom?.let {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(top = 4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Room,
                            contentDescription = null,
                            modifier = Modifier.size(12.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = it,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
            
            // Action Buttons
            when {
                schedule.sessionActive -> {
                    FilledIconButton(
                        onClick = onViewAttendance,
                        colors = IconButtonDefaults.filledIconButtonColors(
                            containerColor = SuccessGreen
                        )
                    ) {
                        Icon(Icons.Outlined.People, contentDescription = "View Attendance")
                    }
                }
                schedule.isStartable && !hasActiveSession -> {
                    FilledIconButton(
                        onClick = onStartClass,
                        colors = IconButtonDefaults.filledIconButtonColors(
                            containerColor = PrimaryBlue
                        )
                    ) {
                        Icon(Icons.Default.PlayArrow, contentDescription = "Start Class")
                    }
                }
                schedule.isCompleted -> {
                    IconButton(onClick = onViewAttendance) {
                        Icon(
                            Icons.Outlined.Assessment,
                            contentDescription = "View Report",
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun EmptyScheduleCard() {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(32.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = Icons.Outlined.EventBusy,
                contentDescription = null,
                modifier = Modifier.size(48.dp),
                tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
            )
            Spacer(modifier = Modifier.height(12.dp))
            Text(
                text = "No Classes Today",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                text = "Enjoy your day!",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
            )
        }
    }
}
