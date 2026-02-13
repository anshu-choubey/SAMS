package com.sams.app.ui.teacher

import androidx.compose.animation.*
import androidx.compose.foundation.background
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClassAttendanceScreen(
    scheduleId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onEndClass: () -> Unit
) {
    val attendanceState by viewModel.attendanceState.collectAsState()
    val sessionState by viewModel.sessionState.collectAsState()
    var showEndClassDialog by remember { mutableStateOf(false) }
    var showManualMarkDialog by remember { mutableStateOf<StudentAttendanceStatus?>(null) }
    var currentSessionId by remember { mutableStateOf<Int?>(null) }
    val snackbarHostState = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()
    
    LaunchedEffect(scheduleId) {
        viewModel.loadClassAttendance(scheduleId)
    }
    
    // Update currentSessionId when attendance loads
    LaunchedEffect(attendanceState) {
        if (attendanceState is TeacherUiState.AttendanceSuccess) {
            val data = (attendanceState as TeacherUiState.AttendanceSuccess).data
            if (data.sessionActive && data.sessionId != null) {
                currentSessionId = data.sessionId
                viewModel.setActiveSessionId(data.sessionId)
            }
            // Auto-refresh every 10 seconds when session active
            if (data.sessionActive) {
                kotlinx.coroutines.delay(10000)
                viewModel.loadClassAttendance(scheduleId)
            }
        }
    }
    
    // Handle end class success/error
    LaunchedEffect(sessionState) {
        when (sessionState) {
            is TeacherUiState.SessionSuccess -> {
                viewModel.resetSessionState()
                onEndClass()
            }
            is TeacherUiState.Error -> {
                val errorMsg = (sessionState as TeacherUiState.Error).message
                scope.launch {
                    snackbarHostState.showSnackbar(errorMsg)
                }
                viewModel.resetSessionState()
            }
            else -> {}
        }
    }
    
    if (showEndClassDialog) {
        AlertDialog(
            onDismissRequest = { showEndClassDialog = false },
            icon = { Icon(Icons.Outlined.StopCircle, contentDescription = null) },
            title = { Text("End Class Session?") },
            text = { 
                Text("This will close the attendance marking for students. You cannot restart this session.")
            },
            confirmButton = {
                Button(
                    onClick = {
                        showEndClassDialog = false
                        currentSessionId?.let { sessionId ->
                            viewModel.endSessionWithId(sessionId)
                        } ?: viewModel.endSession()
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = ErrorRed)
                ) {
                    Text("End Class")
                }
            },
            dismissButton = {
                TextButton(onClick = { showEndClassDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
    
    showManualMarkDialog?.let { student ->
        AlertDialog(
            onDismissRequest = { showManualMarkDialog = null },
            icon = { Icon(Icons.Outlined.HowToReg, contentDescription = null) },
            title = { Text("Mark Attendance") },
            text = { 
                Text("Mark ${student.studentName} as present for this class?")
            },
            confirmButton = {
                Button(
                    onClick = {
                        viewModel.markManualAttendance(scheduleId, student.studentId, "present")
                        showManualMarkDialog = null
                    }
                ) {
                    Text("Mark Present")
                }
            },
            dismissButton = {
                TextButton(onClick = { showManualMarkDialog = null }) {
                    Text("Cancel")
                }
            }
        )
    }
    
    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = { Text("Class Attendance", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.loadClassAttendance(scheduleId) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
                )
            )
        }
    ) { padding ->
        when (val state = attendanceState) {
            is TeacherUiState.Loading -> LoadingScreen()
            is TeacherUiState.Error -> ErrorScreen(
                message = state.message,
                onRetry = { viewModel.loadClassAttendance(scheduleId) }
            )
            is TeacherUiState.AttendanceSuccess -> {
                AttendanceContent(
                    data = state.data,
                    modifier = Modifier.padding(padding),
                    isEndingSession = sessionState is TeacherUiState.Loading,
                    onEndClass = { showEndClassDialog = true },
                    onMarkStudent = { showManualMarkDialog = it }
                )
            }
            else -> {}
        }
    }
}

@Composable
private fun AttendanceContent(
    data: ClassAttendanceResponse,
    modifier: Modifier = Modifier,
    isEndingSession: Boolean,
    onEndClass: () -> Unit,
    onMarkStudent: (StudentAttendanceStatus) -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Subject Info Card
        item {
            SubjectInfoCard(data = data)
        }
        
        // Stats Card
        item {
            AttendanceStatsCard(
                present = data.presentCount,
                total = data.totalStudents,
                sessionActive = data.sessionActive
            )
        }
        
        // End Class Button (if session is active)
        if (data.sessionActive) {
            item {
                Button(
                    onClick = onEndClass,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = ErrorRed),
                    shape = RoundedCornerShape(12.dp),
                    enabled = !isEndingSession
                ) {
                    if (isEndingSession) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            strokeWidth = 2.dp,
                            color = Color.White
                        )
                    } else {
                        Icon(Icons.Default.Stop, contentDescription = null)
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("End Class Session")
                }
            }
        }
        
        // Student List Header
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Student List",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${data.students.size} students",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        
        // Filter Tabs
        item {
            FilterTabs(
                presentCount = data.presentCount,
                absentCount = data.totalStudents - data.presentCount
            )
        }
        
        // Student List
        if (data.students.isEmpty()) {
            item {
                EmptyState(
                    icon = Icons.Outlined.People,
                    title = "No Students",
                    description = "No students found for this class"
                )
            }
        } else {
            items(data.students) { student ->
                StudentAttendanceCard(
                    student = student,
                    canManualMark = data.sessionActive && student.status?.lowercase() != "present",
                    onMarkPresent = { onMarkStudent(student) }
                )
            }
        }
        
        item { Spacer(modifier = Modifier.height(16.dp)) }
    }
}

@Composable
private fun SubjectInfoCard(data: ClassAttendanceResponse) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(PrimaryBlueContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.MenuBook,
                    contentDescription = null,
                    tint = PrimaryBlue
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = data.subjectName,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = data.subjectCode,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                if (data.semester != null && data.section != null) {
                    Text(
                        text = "Semester ${data.semester} • ${data.section}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            if (data.sessionActive) {
                StatusBadge(status = "Live")
            }
        }
    }
}

@Composable
private fun AttendanceStatsCard(
    present: Int,
    total: Int,
    sessionActive: Boolean
) {
    val percentage = if (total > 0) (present * 100.0) / total else 0.0
    val absent = total - present
    
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Attendance Overview",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )
                if (sessionActive) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(CircleShape)
                                .background(SuccessGreen)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Live",
                            style = MaterialTheme.typography.labelSmall,
                            color = SuccessGreen,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Circular Progress
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly,
                verticalAlignment = Alignment.CenterVertically
            ) {
                CircularProgress(
                    percentage = percentage,
                )
                
                Column {
                    StatRow(
                        label = "Present",
                        value = present.toString(),
                        color = SuccessGreen
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    StatRow(
                        label = "Absent",
                        value = absent.toString(),
                        color = ErrorRed
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    StatRow(
                        label = "Total",
                        value = total.toString(),
                        color = MaterialTheme.colorScheme.primary
                    )
                }
            }
        }
    }
}

@Composable
private fun StatRow(
    label: String,
    value: String,
    color: Color
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Box(
            modifier = Modifier
                .size(12.dp)
                .clip(CircleShape)
                .background(color)
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.width(60.dp)
        )
        Text(
            text = value,
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
private fun FilterTabs(
    presentCount: Int,
    absentCount: Int
) {
    var selectedTab by remember { mutableStateOf(0) }
    
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        FilterChip(
            selected = selectedTab == 0,
            onClick = { selectedTab = 0 },
            label = { Text("All") },
            leadingIcon = if (selectedTab == 0) {
                { Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp)) }
            } else null
        )
        FilterChip(
            selected = selectedTab == 1,
            onClick = { selectedTab = 1 },
            label = { Text("Present ($presentCount)") },
            colors = FilterChipDefaults.filterChipColors(
                selectedContainerColor = SuccessGreenContainer,
                selectedLabelColor = SuccessGreen
            ),
            leadingIcon = if (selectedTab == 1) {
                { Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp), tint = SuccessGreen) }
            } else null
        )
        FilterChip(
            selected = selectedTab == 2,
            onClick = { selectedTab = 2 },
            label = { Text("Absent ($absentCount)") },
            colors = FilterChipDefaults.filterChipColors(
                selectedContainerColor = ErrorRedContainer,
                selectedLabelColor = ErrorRed
            ),
            leadingIcon = if (selectedTab == 2) {
                { Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp), tint = ErrorRed) }
            } else null
        )
    }
}

@Composable
private fun StudentAttendanceCard(
    student: StudentAttendanceStatus,
    canManualMark: Boolean,
    onMarkPresent: () -> Unit
) {
    val isPresent = student.status?.lowercase() == "present"
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Avatar
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(
                        if (isPresent) SuccessGreenContainer
                        else MaterialTheme.colorScheme.surfaceVariant
                    ),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = student.studentName.firstOrNull()?.uppercase() ?: "S",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = if (isPresent) SuccessGreen
                    else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = student.studentName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = student.rollNumber,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                student.markedAt?.let { time ->
                    Text(
                        text = "Marked at $time",
                        style = MaterialTheme.typography.labelSmall,
                        color = SuccessGreen
                    )
                }
            }
            
            if (isPresent) {
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .clip(CircleShape)
                        .background(SuccessGreenContainer),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Check,
                        contentDescription = "Present",
                        tint = SuccessGreen,
                        modifier = Modifier.size(18.dp)
                    )
                }
            } else if (canManualMark) {
                FilledTonalIconButton(
                    onClick = onMarkPresent,
                    colors = IconButtonDefaults.filledTonalIconButtonColors(
                        containerColor = PrimaryBlueContainer
                    )
                ) {
                    Icon(
                        imageVector = Icons.Outlined.HowToReg,
                        contentDescription = "Mark Present",
                        tint = PrimaryBlue
                    )
                }
            } else {
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .clip(CircleShape)
                        .background(ErrorRedContainer),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Close,
                        contentDescription = "Absent",
                        tint = ErrorRed,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
        }
    }
}
