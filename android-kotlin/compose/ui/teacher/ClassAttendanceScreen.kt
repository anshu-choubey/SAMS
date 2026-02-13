package com.sams.app.ui.teacher

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.ClassAttendanceResponse
import com.sams.app.data.models.StudentAttendanceStatus
import com.sams.app.ui.theme.PresentColor
import com.sams.app.ui.theme.AbsentColor

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClassAttendanceScreen(
    scheduleId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    val uiState by viewModel.classAttendanceState.collectAsState()
    val attendanceActionState by viewModel.attendanceState.collectAsState()
    
    LaunchedEffect(scheduleId) {
        viewModel.loadClassAttendance(scheduleId)
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Class Attendance") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.loadClassAttendance(scheduleId) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
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
                val data = state.data as ClassAttendanceResponse
                ClassAttendanceContent(
                    data = data,
                    scheduleId = scheduleId,
                    modifier = Modifier.padding(padding),
                    onMarkPresent = { studentId -> 
                        viewModel.markManualAttendance(scheduleId, studentId, "present")
                    },
                    onMarkAbsent = { studentId ->
                        viewModel.markManualAttendance(scheduleId, studentId, "absent")
                    }
                )
            }
            is TeacherUiState.Error -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(state.message, color = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.loadClassAttendance(scheduleId) }) {
                            Text("Retry")
                        }
                    }
                }
            }
            else -> {}
        }
        
        // Show loading overlay when marking attendance
        if (attendanceActionState is TeacherUiState.Loading) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(MaterialTheme.colorScheme.surface.copy(alpha = 0.7f)),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator()
            }
        }
    }
}

@Composable
private fun ClassAttendanceContent(
    data: ClassAttendanceResponse,
    scheduleId: Int,
    modifier: Modifier = Modifier,
    onMarkPresent: (Int) -> Unit,
    onMarkAbsent: (Int) -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Summary Card
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        data.subjectName,
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        data.subjectCode,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.primary
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceEvenly
                    ) {
                        StatItem(
                            value = "${data.totalStudents}",
                            label = "Total"
                        )
                        StatItem(
                            value = "${data.presentCount}",
                            label = "Present",
                            color = PresentColor
                        )
                        StatItem(
                            value = "${data.absentCount}",
                            label = "Absent",
                            color = AbsentColor
                        )
                        StatItem(
                            value = "${(data.presentCount * 100 / maxOf(data.totalStudents, 1))}%",
                            label = "Rate"
                        )
                    }
                }
            }
        }
        
        // Session Status
        if (data.sessionActive) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = PresentColor.copy(alpha = 0.1f)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(12.dp)
                                .clip(CircleShape)
                                .background(PresentColor)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            "Session Active - Students can mark attendance",
                            style = MaterialTheme.typography.bodyMedium,
                            color = PresentColor
                        )
                    }
                }
            }
        }
        
        item {
            Text(
                "Students",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
        }
        
        // Student List
        items(data.students) { student ->
            StudentAttendanceCard(
                student = student,
                sessionActive = data.sessionActive,
                onMarkPresent = { onMarkPresent(student.studentId) },
                onMarkAbsent = { onMarkAbsent(student.studentId) }
            )
        }
    }
}

@Composable
private fun StatItem(
    value: String,
    label: String,
    color: androidx.compose.ui.graphics.Color = MaterialTheme.colorScheme.onPrimaryContainer
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            value,
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            color = color
        )
        Text(
            label,
            style = MaterialTheme.typography.bodySmall
        )
    }
}

@Composable
private fun StudentAttendanceCard(
    student: StudentAttendanceStatus,
    sessionActive: Boolean,
    onMarkPresent: () -> Unit,
    onMarkAbsent: () -> Unit
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Avatar
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(
                        when (student.status) {
                            "present" -> PresentColor.copy(alpha = 0.2f)
                            "absent" -> AbsentColor.copy(alpha = 0.2f)
                            else -> MaterialTheme.colorScheme.surfaceVariant
                        }
                    ),
                contentAlignment = Alignment.Center
            ) {
                when (student.status) {
                    "present" -> Icon(
                        Icons.Default.CheckCircle,
                        contentDescription = null,
                        tint = PresentColor
                    )
                    "absent" -> Icon(
                        Icons.Default.Cancel,
                        contentDescription = null,
                        tint = AbsentColor
                    )
                    else -> Text(
                        student.studentName.firstOrNull()?.uppercase() ?: "S",
                        style = MaterialTheme.typography.titleMedium
                    )
                }
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    student.studentName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    student.rollNumber,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                student.markedAt?.let {
                    Text(
                        "Marked at: $it",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                student.faceConfidence?.let {
                    Text(
                        "Face match: ${it.toInt()}%",
                        style = MaterialTheme.typography.labelSmall,
                        color = if (it >= 85) PresentColor else AbsentColor
                    )
                }
            }
            
            if (sessionActive) {
                Row {
                    IconButton(
                        onClick = onMarkPresent,
                        colors = IconButtonDefaults.iconButtonColors(
                            containerColor = if (student.status == "present") 
                                PresentColor.copy(alpha = 0.2f) else MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Icon(
                            Icons.Default.Check,
                            contentDescription = "Mark Present",
                            tint = PresentColor
                        )
                    }
                    IconButton(
                        onClick = onMarkAbsent,
                        colors = IconButtonDefaults.iconButtonColors(
                            containerColor = if (student.status == "absent")
                                AbsentColor.copy(alpha = 0.2f) else MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Icon(
                            Icons.Default.Close,
                            contentDescription = "Mark Absent",
                            tint = AbsentColor
                        )
                    }
                }
            } else {
                // Just show status badge
                AssistChip(
                    onClick = { },
                    label = { 
                        Text(
                            when (student.status) {
                                "present" -> "Present"
                                "absent" -> "Absent"
                                else -> "N/A"
                            }
                        )
                    },
                    colors = AssistChipDefaults.assistChipColors(
                        containerColor = when (student.status) {
                            "present" -> PresentColor.copy(alpha = 0.2f)
                            "absent" -> AbsentColor.copy(alpha = 0.2f)
                            else -> MaterialTheme.colorScheme.surfaceVariant
                        }
                    )
                )
            }
        }
    }
}
