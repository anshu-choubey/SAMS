package com.sams.app.ui.teacher

import android.util.Log
import androidx.compose.animation.*
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.sams.app.data.models.ClassAttendanceResponse
import com.sams.app.data.models.StudentAttendanceStatus
import com.sams.app.ui.theme.*
import timber.log.Timber

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClassAttendanceScreen(
    scheduleId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    val uiState by viewModel.classAttendanceState.collectAsState()
    val attendanceActionState by viewModel.attendanceState.collectAsState()
    val sessionState by viewModel.sessionState.collectAsState()
    val isDark = isSystemInDarkTheme()

    LaunchedEffect(scheduleId) { viewModel.loadClassAttendance(scheduleId) }

    // ✅ FIX: Navigate back to dashboard after successfully ending the class
    LaunchedEffect(sessionState) {
        when (sessionState) {
            is TeacherUiState.Success -> {
                // Session ended successfully - go back to dashboard
                Log.d("ClassAttendanceScreen", "Session ended successfully, navigating back")
                onBack()
                viewModel.resetSessionState()
            }
            is TeacherUiState.Error -> {
                // Error occurred, but don't navigate away - show error message
                Log.d("ClassAttendanceScreen", "Error ending session: ${(sessionState as TeacherUiState.Error).message}")
            }
            else -> {} // Loading or Idle - do nothing
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = "Class Attendance",
                        style = MaterialTheme.typography.titleLarge.copy(
                            fontWeight = FontWeight.Bold
                        )
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.loadClassAttendance(scheduleId) }) {
                        Icon(
                            Icons.Default.Refresh,
                            contentDescription = "Refresh",
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface,
                    titleContentColor = MaterialTheme.colorScheme.onSurface,
                    navigationIconContentColor = MaterialTheme.colorScheme.onSurface
                )
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { padding ->

        Box(modifier = Modifier.fillMaxSize()) {

            AnimatedContent(
                targetState = uiState,
                label = "classAttendanceState",
                transitionSpec = {
                    fadeIn(tween(300)) togetherWith fadeOut(tween(200))
                }
            ) { state ->
                when (state) {

                    // ── Loading ───────────────────────────────
                    is TeacherUiState.Loading -> {
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .padding(padding),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                CircularProgressIndicator(
                                    color = MaterialTheme.colorScheme.primary,
                                    strokeWidth = 3.dp
                                )
                                Text(
                                    text = "Loading attendance...",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                    }

                    // ── Success ───────────────────────────────
                    is TeacherUiState.Success -> {
                        ClassAttendanceContent(
                            data = state.data as ClassAttendanceResponse,
                            scheduleId = scheduleId,
                            modifier = Modifier.padding(padding),
                            isDark = isDark,
                            isSessionLoading = sessionState is TeacherUiState.Loading,
                            onEndClass = { viewModel.endSession() },
                            onMarkPresent = { studentId ->
                                viewModel.markManualAttendance(scheduleId, studentId, "present")
                            },
                            onMarkAbsent = { studentId ->
                                viewModel.markManualAttendance(scheduleId, studentId, "absent")
                            }
                        )
                    }

                    // ── Error ─────────────────────────────────
                    is TeacherUiState.Error -> {
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .padding(padding)
                                .padding(horizontal = 32.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Card(
                                shape = RoundedCornerShape(24.dp),
                                colors = CardDefaults.cardColors(
                                    containerColor = MaterialTheme.colorScheme.errorContainer
                                ),
                                elevation = CardDefaults.cardElevation(0.dp)
                            ) {
                                Column(
                                    modifier = Modifier.padding(32.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(72.dp)
                                            .clip(CircleShape)
                                            .background(
                                                MaterialTheme.colorScheme.error.copy(alpha = 0.12f)
                                            ),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            Icons.Default.ErrorOutline,
                                            contentDescription = null,
                                            modifier = Modifier.size(36.dp),
                                            tint = MaterialTheme.colorScheme.error
                                        )
                                    }
                                    Text(
                                        text = state.message,
                                        style = MaterialTheme.typography.bodyMedium,
                                        color = MaterialTheme.colorScheme.onErrorContainer
                                    )
                                    Button(
                                        onClick = { viewModel.loadClassAttendance(scheduleId) },
                                        shape = RoundedCornerShape(12.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = MaterialTheme.colorScheme.error
                                        )
                                    ) {
                                        Icon(
                                            Icons.Default.Refresh,
                                            contentDescription = null,
                                            modifier = Modifier.size(18.dp)
                                        )
                                        Spacer(Modifier.width(6.dp))
                                        Text("Retry")
                                    }
                                }
                            }
                        }
                    }

                    else -> {}
                }
            }

            // ── Marking Overlay ───────────────────────────
            AnimatedVisibility(
                visible = attendanceActionState is TeacherUiState.Loading,
                enter = fadeIn(tween(200)),
                exit = fadeOut(tween(200)),
                modifier = Modifier.fillMaxSize()
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(MaterialTheme.colorScheme.scrim.copy(alpha = 0.45f)),
                    contentAlignment = Alignment.Center
                ) {
                    Card(
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        ),
                        elevation = CardDefaults.cardElevation(
                            defaultElevation = if (isDark) 8.dp else 4.dp
                        )
                    ) {
                        Row(
                            modifier = Modifier.padding(20.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(24.dp),
                                strokeWidth = 2.5.dp,
                                color = MaterialTheme.colorScheme.primary
                            )
                            Text(
                                text = "Updating attendance...",
                                style = MaterialTheme.typography.titleSmall.copy(
                                    fontWeight = FontWeight.Medium
                                ),
                                color = MaterialTheme.colorScheme.onSurface
                            )
                        }
                    }
                }
            }
        }
    }
}

// ── Class Attendance Content ──────────────────────────────────────────────────

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ClassAttendanceContent(
    data: ClassAttendanceResponse,
    isSessionLoading: Boolean = false,
    onEndClass: () -> Unit = {},
    scheduleId: Int,
    modifier: Modifier = Modifier,
    isDark: Boolean,
    onMarkPresent: (Int) -> Unit,
    onMarkAbsent: (Int) -> Unit
) {
    var searchQuery by remember { mutableStateOf("") }
    var filterStatus by remember { mutableStateOf("all") }

    // ── Session Active Check ──────────────────────────────
    val canEndClass = data.sessionActive
    Log.d("ClassAttendance", "sessionActive: ${data.sessionActive}")

    val filteredStudents = remember(data.students, searchQuery, filterStatus) {
        data.students
            .filter { student ->
                searchQuery.isEmpty() ||
                        student.studentName.contains(searchQuery, ignoreCase = true) ||
                        student.rollNumber.contains(searchQuery, ignoreCase = true)
            }
            .filter { student ->
                filterStatus == "all" || student.status == filterStatus
            }
    }

    val attendanceRate = (data.presentCount * 100 / maxOf(data.totalStudents, 1))

    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {

        // ── Summary Banner ────────────────────────────────
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(24.dp),
                elevation = CardDefaults.cardElevation(
                    defaultElevation = if (isDark) 6.dp else 2.dp
                )
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            Brush.linearGradient(
                                colors = listOf(
                                    MaterialTheme.colorScheme.primary,
                                    MaterialTheme.colorScheme.tertiary
                                )
                            )
                        )
                        .padding(24.dp)
                ) {
                    Column {
                        Text(
                            text = data.subjectName,
                            style = MaterialTheme.typography.titleLarge.copy(
                                fontWeight = FontWeight.ExtraBold
                            ),
                            color = Color.White,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = data.subjectCode,
                            style = MaterialTheme.typography.bodyMedium,
                            color = Color.White.copy(alpha = 0.8f)
                        )

                        Spacer(Modifier.height(16.dp))

                        // Progress bar
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(8.dp)
                                .clip(RoundedCornerShape(4.dp))
                                .background(Color.White.copy(alpha = 0.25f))
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth(
                                        (attendanceRate / 100f).coerceIn(0f, 1f)
                                    )
                                    .fillMaxHeight()
                                    .clip(RoundedCornerShape(4.dp))
                                    .background(Color.White)
                            )
                        }

                        Spacer(Modifier.height(6.dp))

                        Text(
                            text = "$attendanceRate% attendance rate",
                            style = MaterialTheme.typography.labelSmall,
                            color = Color.White.copy(alpha = 0.75f)
                        )

                        Spacer(Modifier.height(14.dp))

                        // Stats row
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceEvenly,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            BannerStatItem("${data.totalStudents}", "Total", Color.White)
                            Box(
                                Modifier
                                    .width(1.dp)
                                    .height(36.dp)
                                    .background(Color.White.copy(alpha = 0.3f))
                            )
                            BannerStatItem("${data.presentCount}", "Present", Color.White)
                            Box(
                                Modifier
                                    .width(1.dp)
                                    .height(36.dp)
                                    .background(Color.White.copy(alpha = 0.3f))
                            )
                            BannerStatItem(
                                "${data.absentCount}",
                                "Absent",
                                Color.White.copy(alpha = 0.85f)
                            )
                            Box(
                                Modifier
                                    .width(1.dp)
                                    .height(36.dp)
                                    .background(Color.White.copy(alpha = 0.3f))
                            )
                            BannerStatItem("$attendanceRate%", "Rate", Color.White)
                        }
                    }
                }
            }
        }

        // ── Class Control Buttons ─────────────────────────
        item {
            // End Class Button - only show when session is active
            if (canEndClass) {
                Button(
                    onClick = onEndClass,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = AbsentColor,
                        contentColor = Color.White
                    ),
                    enabled = !isSessionLoading
                ) {
                    Icon(
                        Icons.Default.Stop,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(
                        text = "End Class",
                        style = MaterialTheme.typography.labelLarge.copy(
                            fontWeight = FontWeight.Bold
                        )
                    )
                }
            }
        }

        // ── Live Session Banner ───────────────────────────
        if (data.sessionActive) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = PresentColor.copy(alpha = 0.08f)
                    ),
                    border = BorderStroke(1.dp, PresentColor.copy(alpha = 0.3f)),
                    elevation = CardDefaults.cardElevation(0.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(14.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(9.dp)
                                .clip(CircleShape)
                                .background(PresentColor)
                        )
                        Text(
                            text = "LIVE — Students can mark attendance",
                            style = MaterialTheme.typography.labelMedium.copy(
                                fontWeight = FontWeight.ExtraBold
                            ),
                            color = PresentColor
                        )
                    }
                }
            }
        }

        // ── Search + Filter ───────────────────────────────
        item {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    placeholder = {
                        Text(
                            text = "Search by name or roll number",
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    },
                    leadingIcon = {
                        Icon(
                            Icons.Default.Search,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    },
                    trailingIcon = {
                        if (searchQuery.isNotEmpty()) {
                            IconButton(onClick = { searchQuery = "" }) {
                                Icon(
                                    Icons.Default.Close,
                                    contentDescription = "Clear",
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                    },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = MaterialTheme.colorScheme.primary,
                        unfocusedBorderColor = MaterialTheme.colorScheme.onSurfaceVariant
                            .copy(alpha = 0.4f),
                        focusedTextColor = MaterialTheme.colorScheme.onSurface,
                        unfocusedTextColor = MaterialTheme.colorScheme.onSurface,
                        cursorColor = MaterialTheme.colorScheme.primary
                    )
                )

                // Filter chips
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(
                        Triple("all", "All", MaterialTheme.colorScheme.primaryContainer),
                        Triple("present", "Present ✓", PresentColor.copy(alpha = 0.15f)),
                        Triple("absent", "Absent ✗", AbsentColor.copy(alpha = 0.15f))
                    ).forEach { (value, label, selectedBg) ->
                        FilterChip(
                            selected = filterStatus == value,
                            onClick = { filterStatus = value },
                            label = {
                                Text(
                                    text = label,
                                    style = MaterialTheme.typography.labelMedium.copy(
                                        fontWeight = FontWeight.SemiBold
                                    )
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                // ✅ Alpha-based — works in dark mode
                                selectedContainerColor = selectedBg,
                                selectedLabelColor = when (value) {
                                    "present" -> PresentColor
                                    "absent" -> AbsentColor
                                    else -> MaterialTheme.colorScheme.onPrimaryContainer
                                },
                                containerColor = MaterialTheme.colorScheme.surfaceVariant,
                                labelColor = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        )
                    }
                }
            }
        }

        // ── Students Section Header ───────────────────────
        item {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth()
            ) {
                Box(
                    modifier = Modifier
                        .width(4.dp)
                        .height(20.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(MaterialTheme.colorScheme.primary)
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = "Students",
                    style = MaterialTheme.typography.titleMedium.copy(
                        fontWeight = FontWeight.Bold
                    ),
                    color = MaterialTheme.colorScheme.onBackground
                )
                Spacer(Modifier.weight(1f))
                Surface(
                    shape = RoundedCornerShape(50.dp),
                    color = MaterialTheme.colorScheme.primaryContainer
                ) {
                    Text(
                        text = "${filteredStudents.size} shown",
                        style = MaterialTheme.typography.labelSmall.copy(
                            fontWeight = FontWeight.Medium
                        ),
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                    )
                }
            }
        }

        // ── Empty State ───────────────────────────────────
        if (filteredStudents.isEmpty()) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                    ),
                    elevation = CardDefaults.cardElevation(0.dp)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(40.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(
                            Icons.Outlined.PersonSearch,
                            contentDescription = null,
                            modifier = Modifier.size(48.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(
                            text = "No students found",
                            style = MaterialTheme.typography.bodyMedium.copy(
                                fontWeight = FontWeight.Medium
                            ),
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        if (searchQuery.isNotEmpty()) {
                            TextButton(onClick = { searchQuery = "" }) {
                                Text("Clear search")
                            }
                        }
                    }
                }
            }
        } else {
            items(filteredStudents, key = { it.studentId }) { student ->
                StudentAttendanceCard(
                    student = student,
                    sessionActive = data.sessionActive,
                    isDark = isDark,
                    onMarkPresent = { onMarkPresent(student.studentId) },
                    onMarkAbsent = { onMarkAbsent(student.studentId) }
                )
            }
        }

        item { Spacer(Modifier.height(16.dp)) }
    }
}

// ── Banner Stat Item ──────────────────────────────────────────────────────────

@Composable
private fun BannerStatItem(value: String, label: String, color: Color) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        Text(
            text = value,
            style = MaterialTheme.typography.titleLarge.copy(
                fontWeight = FontWeight.ExtraBold
            ),
            color = color
        )
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = color.copy(alpha = 0.75f)
        )
    }
}

// ── Student Attendance Card ───────────────────────────────────────────────────

@Composable
private fun StudentAttendanceCard(
    student: StudentAttendanceStatus,
    sessionActive: Boolean,
    isDark: Boolean,
    onMarkPresent: () -> Unit,
    onMarkAbsent: () -> Unit
) {
    val isPresent = student.status == "present"
    val isAbsent = student.status == "absent"
    val hasStatus = isPresent || isAbsent

    val statusColor = when (student.status) {
        "present" -> PresentColor
        "absent" -> AbsentColor
        else -> MaterialTheme.colorScheme.onSurfaceVariant
    }

    // ✅ Alpha-based containers — dark mode safe
    val statusContainerColor = when (student.status) {
        "present" -> PresentColor.copy(alpha = 0.12f)
        "absent" -> AbsentColor.copy(alpha = 0.12f)
        else -> MaterialTheme.colorScheme.surfaceVariant
    }

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(
            defaultElevation = if (isDark) 2.dp else 1.dp
        )
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {

            // ── Student Photo (Face or Avatar) ────────────
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .clip(CircleShape)
                    .background(statusContainerColor),
                contentAlignment = Alignment.Center
            ) {
                // Display student initial or status
                if (student.faceRegistered) {
                    Icon(
                        imageVector = Icons.Filled.Face,
                        contentDescription = "Face verified",
                        tint = Color.White,
                        modifier = Modifier.size(32.dp)
                    )
                } else if (hasStatus) {
                    Icon(
                        imageVector = if (isPresent) Icons.Default.CheckCircle
                        else Icons.Default.Cancel,
                        contentDescription = null,
                        tint = statusColor,
                        modifier = Modifier.size(26.dp)
                    )
                } else {
                    Text(
                        text = student.studentName.firstOrNull()?.uppercase() ?: "S",
                        style = MaterialTheme.typography.titleMedium.copy(
                            fontWeight = FontWeight.Bold
                        ),
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }

            Spacer(Modifier.width(12.dp))

            // ── Student Info ──────────────────────────────
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = student.studentName,
                    style = MaterialTheme.typography.titleSmall.copy(
                        fontWeight = FontWeight.Bold
                    ),
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = student.rollNumber,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                student.markedAt?.let {
                    Text(
                        text = "At $it",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                student.faceConfidence?.let { confidence ->
                    val confColor = if (confidence >= 85) PresentColor else AbsentColor
                    Surface(
                        shape = RoundedCornerShape(50.dp),
                        color = confColor.copy(alpha = 0.12f),
                        modifier = Modifier.padding(top = 3.dp)
                    ) {
                        Text(
                            text = "${confidence.toInt()}% match",
                            style = MaterialTheme.typography.labelSmall.copy(
                                fontWeight = FontWeight.Medium
                            ),
                            color = confColor,
                            modifier = Modifier.padding(
                                horizontal = 8.dp, vertical = 2.dp
                            )
                        )
                    }
                }
            }

            Spacer(Modifier.width(8.dp))

            // ── Action / Status ───────────────────────────
            if (sessionActive) {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    // Present button
                    IconButton(
                        onClick = onMarkPresent,
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(
                                if (isPresent) PresentColor.copy(alpha = 0.15f)
                                else MaterialTheme.colorScheme.surfaceVariant
                            )
                    ) {
                        Icon(
                            Icons.Default.Check,
                            contentDescription = "Mark Present",
                            tint = if (isPresent) PresentColor
                            else MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    // Absent button
                    IconButton(
                        onClick = onMarkAbsent,
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(
                                if (isAbsent) AbsentColor.copy(alpha = 0.15f)
                                else MaterialTheme.colorScheme.surfaceVariant
                            )
                    ) {
                        Icon(
                            Icons.Default.Close,
                            contentDescription = "Mark Absent",
                            tint = if (isAbsent) AbsentColor
                            else MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
            } else {
                // Status chip
                Surface(
                    shape = RoundedCornerShape(50.dp),
                    color = statusContainerColor
                ) {
                    Text(
                        text = when (student.status) {
                            "present" -> "Present"
                            "absent" -> "Absent"
                            else -> "N/A"
                        },
                        style = MaterialTheme.typography.labelMedium.copy(
                            fontWeight = FontWeight.Bold
                        ),
                        color = statusColor,
                        modifier = Modifier.padding(
                            horizontal = 12.dp, vertical = 6.dp
                        )
                    )
                }
            }
        }
    }
}
