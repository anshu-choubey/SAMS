package com.sams.app.ui.student

import androidx.compose.foundation.background
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AttendanceHistoryScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    val uiState by viewModel.historyState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadHistory()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Attendance History", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
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
                onRetry = { viewModel.loadHistory() }
            )
            is StudentUiState.Success -> {
                val response = state.data as AttendanceHistoryResponse
                val records = response.data?.records ?: emptyList()
                HistoryContent(
                    records = records,
                    modifier = Modifier.padding(padding)
                )
            }
            else -> {}
        }
    }
}

@Composable
private fun HistoryContent(
    records: List<AttendanceRecord>,
    modifier: Modifier = Modifier
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Summary Card based on records
        if (records.isNotEmpty()) {
            item {
                val attended = records.count { it.status.lowercase() == "present" }
                val total = records.size
                val percentage = if (total > 0) (attended.toDouble() / total) * 100 else 0.0
                SummaryCard(totalClasses = total, attended = attended, percentage = percentage)
            }
        }
        
        item {
            SectionHeader(title = "Recent Attendance")
        }
        
        if (records.isEmpty()) {
            item {
                EmptyState(
                    icon = Icons.Outlined.History,
                    title = "No Records",
                    description = "No attendance records found"
                )
            }
        } else {
            items(records) { record ->
                AttendanceRecordCard(record = record)
            }
        }
    }
}

@Composable
private fun SummaryCard(totalClasses: Int, attended: Int, percentage: Double) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(20.dp)) {
            Text(
                text = "Overall Summary",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceAround,
                verticalAlignment = Alignment.CenterVertically
            ) {
                CircularProgress(
                    percentage = percentage,
                    size = 100.dp,
                    strokeWidth = 10.dp
                )
                
                Column {
                    StatItem(
                        label = "Total Classes",
                        value = totalClasses.toString(),
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    StatItem(
                        label = "Present",
                        value = attended.toString(),
                        color = SuccessGreen
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    StatItem(
                        label = "Absent",
                        value = (totalClasses - attended).toString(),
                        color = ErrorRed
                    )
                }
            }
        }
    }
}

@Composable
private fun StatItem(
    label: String,
    value: String,
    color: androidx.compose.ui.graphics.Color
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            modifier = Modifier
                .size(12.dp)
                .clip(CircleShape)
                .background(color)
        )
        Spacer(modifier = Modifier.width(8.dp))
        Column {
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                text = value,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = color
            )
        }
    }
}

@Composable
private fun AttendanceRecordCard(record: AttendanceRecord) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Status Icon
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(
                        when (record.status.lowercase()) {
                            "present" -> SuccessGreenContainer
                            "absent" -> ErrorRedContainer
                            else -> WarningOrangeContainer
                        }
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = when (record.status.lowercase()) {
                        "present" -> Icons.Default.Check
                        "absent" -> Icons.Default.Close
                        else -> Icons.Default.AccessTime
                    },
                    contentDescription = null,
                    tint = when (record.status.lowercase()) {
                        "present" -> SuccessGreen
                        "absent" -> ErrorRed
                        else -> WarningOrange
                    }
                )
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = record.subjectName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = "${record.subjectCode} • ${record.teacherName}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(top = 4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Outlined.CalendarToday,
                        contentDescription = null,
                        modifier = Modifier.size(12.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = record.date,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.width(12.dp))
                    Icon(
                        imageVector = Icons.Outlined.AccessTime,
                        contentDescription = null,
                        modifier = Modifier.size(12.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = record.time,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            StatusBadge(status = record.status)
        }
    }
}
