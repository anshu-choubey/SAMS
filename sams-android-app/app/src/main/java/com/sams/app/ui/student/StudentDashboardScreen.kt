package com.sams.app.ui.student

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
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.automirrored.filled.Logout
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
    val isRefreshing by viewModel.isRefreshing.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadDashboard()
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
                        Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = "Logout")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
                )
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is StudentUiState.Loading -> LoadingScreen()
            is StudentUiState.Error -> ErrorScreen(
                message = state.message,
                onRetry = { viewModel.loadDashboard() }
            )
            is StudentUiState.Success -> {
                val data = state.data as StudentDashboardData
                PullToRefreshBox(
                    isRefreshing = isRefreshing,
                    onRefresh = { viewModel.refreshDashboard() },
                    modifier = Modifier.padding(padding)
                ) {
                    DashboardContent(
                        data = data,
                        onNavigateToSchedule = onNavigateToSchedule,
                        onNavigateToHistory = onNavigateToHistory,
                        onNavigateToProfile = onNavigateToProfile,
                        onNavigateToFaceRegistration = onNavigateToFaceRegistration,
                        onMarkAttendance = onMarkAttendance
                    )
                }
            }
            else -> {}
        }
    }
}

@Composable
private fun DashboardContent(
    data: StudentDashboardData,
    onNavigateToSchedule: () -> Unit,
    onNavigateToHistory: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToFaceRegistration: () -> Unit,
    onMarkAttendance: (Int, Double, Double, String) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Welcome Card with Profile
        item {
            WelcomeCard(
                profile = data.profile,
                onProfileClick = onNavigateToProfile
            )
        }
        
        // Face Registration Warning
        if (!data.profile.faceRegistered) {
            item {
                FaceRegistrationWarning(onClick = onNavigateToFaceRegistration)
            }
        }
        
        // Active Session Alert
        data.activeSession?.let { session ->
            item {
                ActiveSessionCard(
                    session = session,
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
        
        // Attendance Overview
        item {
            AttendanceOverviewCard(
                stats = data.getAttendance(),
                onViewHistory = onNavigateToHistory
            )
        }
        
        // Quick Actions
        item {
            SectionHeader(title = "Quick Actions")
            QuickActionsGrid(
                onSchedule = onNavigateToSchedule,
                onHistory = onNavigateToHistory,
                onProfile = onNavigateToProfile,
                onFaceRegister = onNavigateToFaceRegistration,
                showFaceRegister = !data.profile.faceRegistered
            )
        }
        
        // Subject-wise Attendance
        if (data.subjectWise.isNotEmpty()) {
            item {
                SectionHeader(
                    title = "Subject Attendance",
                    actionText = "View All",
                    onAction = onNavigateToHistory
                )
            }
            item {
                SubjectAttendanceList(subjects = data.subjectWise)
            }
        }
        
        // Today's Schedule
        item {
            SectionHeader(
                title = "Today's Schedule",
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
                ScheduleCard(
                    schedule = schedule,
                    onMarkAttendance = { 
                        onMarkAttendance(
                            schedule.scheduleId, 
                            schedule.teacherLatitude, 
                            schedule.teacherLongitude, 
                            schedule.subjectName
                        ) 
                    }
                )
            }
        }
        
        // Low Attendance Warning
        if (data.lowAttendanceSubjects.isNotEmpty()) {
            item {
                SectionHeader(title = "Attention Required")
                LowAttendanceWarning(subjects = data.lowAttendanceSubjects)
            }
        }
        
        // Bottom spacing
        item { Spacer(modifier = Modifier.height(16.dp)) }
    }
}

@Composable
private fun WelcomeCard(
    profile: StudentProfile,
    onProfileClick: () -> Unit
) {
    GradientCard(
        modifier = Modifier.fillMaxWidth(),
        gradientColors = listOf(PrimaryBlue, AccentPurple),
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
                    text = (profile.fullName?.firstOrNull() ?: 'S').uppercase(),
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
                    text = profile.fullName ?: "Student",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = profile.rollNumber ?: "",
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
private fun FaceRegistrationWarning(onClick: () -> Unit) {
    SAMSCard(
        modifier = Modifier.fillMaxWidth(),
        onClick = onClick
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(WarningOrangeContainer)
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(WarningOrange.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Face,
                    contentDescription = null,
                    tint = WarningOrange,
                    modifier = Modifier.size(24.dp)
                )
            }
            Spacer(modifier = Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Face Not Registered",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                    color = WarningOrange
                )
                Text(
                    text = "Register your face to mark attendance",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            Icon(
                imageVector = Icons.AutoMirrored.Filled.ArrowForward,
                contentDescription = null,
                tint = WarningOrange
            )
        }
    }
}

@Composable
private fun ActiveSessionCard(
    session: ActiveSession,
    onMarkAttendance: () -> Unit
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
                        text = "LIVE CLASS",
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
                text = "${session.subjectCode} • ${session.teacherName}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            
            session.classroom?.let {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(top = 4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Room,
                        contentDescription = null,
                        modifier = Modifier.size(14.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = it,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Button(
                onClick = onMarkAttendance,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = SuccessGreen
                ),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.Default.Check, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Mark Attendance", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Composable
private fun AttendanceOverviewCard(
    stats: AttendanceStats,
    onViewHistory: () -> Unit
) {
    SAMSCard(
        modifier = Modifier.fillMaxWidth(),
        onClick = onViewHistory
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Attendance Overview",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold
                )
                Icon(
                    imageVector = Icons.Default.ChevronRight,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                CircularProgress(
                    percentage = stats.percentage,
                    size = 80.dp,
                    strokeWidth = 8.dp
                )
                
                Spacer(modifier = Modifier.width(24.dp))
                
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(12.dp)
                                .clip(CircleShape)
                                .background(SuccessGreen)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Present: ${stats.attended}",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(12.dp)
                                .clip(CircleShape)
                                .background(ErrorRed)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Absent: ${stats.totalClasses - stats.attended}",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Total Classes: ${stats.totalClasses}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }
    }
}

@Composable
private fun QuickActionsGrid(
    onSchedule: () -> Unit,
    onHistory: () -> Unit,
    onProfile: () -> Unit,
    onFaceRegister: () -> Unit,
    showFaceRegister: Boolean = true
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
            icon = Icons.Outlined.History,
            label = "History",
            onClick = onHistory,
            containerColor = SecondaryTealContainer,
            contentColor = SecondaryTeal
        )
        QuickActionButton(
            icon = Icons.Outlined.Person,
            label = "Profile",
            onClick = onProfile,
            containerColor = AccentPurpleContainer,
            contentColor = AccentPurple
        )
        if (showFaceRegister) {
            QuickActionButton(
                icon = Icons.Outlined.Face,
                label = "Face ID",
                onClick = onFaceRegister,
                containerColor = WarningOrangeContainer,
                contentColor = WarningOrange
            )
        }
    }
}

@Composable
private fun SubjectAttendanceList(subjects: List<SubjectAttendance>) {
    LazyRow(
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(subjects) { subject ->
            SubjectAttendanceCard(subject = subject)
        }
    }
}

@Composable
private fun SubjectAttendanceCard(subject: SubjectAttendance) {
    SAMSCard(
        modifier = Modifier.width(180.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
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
            
            Spacer(modifier = Modifier.height(12.dp))
            
            AttendanceProgressBar(
                percentage = subject.percentage,
                height = 6.dp
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "${subject.attended}/${subject.totalClasses} classes",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun ScheduleCard(
    schedule: ScheduleItem,
    onMarkAttendance: () -> Unit
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
                            schedule.attendanceMarked -> PrimaryBlue
                            schedule.sessionActive -> SuccessGreen
                            schedule.isActive -> WarningOrange
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
                        schedule.attendanceMarked -> StatusBadge(status = "Present")
                        schedule.sessionActive -> StatusBadge(status = "Ongoing")
                        schedule.isActive -> StatusBadge(status = "Not Started", isWarning = true)
                    }
                }
                Text(
                    text = schedule.subjectCode,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = schedule.teacherName,
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
                
                // Show message when session not started
                if (schedule.isActive && !schedule.sessionActive && !schedule.attendanceMarked) {
                    Spacer(modifier = Modifier.height(8.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Info,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = WarningOrange
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "Waiting for teacher to start class",
                            style = MaterialTheme.typography.labelSmall,
                            color = WarningOrange
                        )
                    }
                }
            }
            
            // Action Button - only show when session is active (teacher started)
            if (schedule.sessionActive && !schedule.attendanceMarked) {
                FilledIconButton(
                    onClick = onMarkAttendance,
                    colors = IconButtonDefaults.filledIconButtonColors(
                        containerColor = SuccessGreen
                    )
                ) {
                    Icon(
                        imageVector = Icons.Default.Check,
                        contentDescription = "Mark Attendance"
                    )
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
                text = "Enjoy your day off!",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
            )
        }
    }
}

@Composable
private fun LowAttendanceWarning(subjects: List<LowAttendanceSubject>) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(ErrorRedContainer.copy(alpha = 0.3f))
                .padding(16.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.Warning,
                    contentDescription = null,
                    tint = ErrorRed,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Low Attendance Alert",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                    color = ErrorRed
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            subjects.forEach { subject ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 4.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = subject.subjectName,
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.weight(1f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Column(horizontalAlignment = Alignment.End) {
                        Text(
                            text = "${subject.percentage.toInt()}%",
                            style = MaterialTheme.typography.labelMedium,
                            fontWeight = FontWeight.Bold,
                            color = ErrorRed
                        )
                        Text(
                            text = "+${subject.classesNeeded} classes needed",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }
    }
}
