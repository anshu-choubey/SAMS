package com.sams.app.ui.student

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.StudentProfileFull
import com.sams.app.ui.theme.PresentColor

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StudentProfileScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    val uiState by viewModel.profileState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadProfile()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Profile") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is StudentUiState.Loading -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            is StudentUiState.Success -> {
                val profile = state.data as StudentProfileFull
                ProfileContent(
                    profile = profile,
                    modifier = Modifier.padding(padding)
                )
            }
            is StudentUiState.Error -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(state.message, color = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.loadProfile() }) {
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
private fun ProfileContent(
    profile: StudentProfileFull,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Avatar
        Card(
            modifier = Modifier.size(120.dp),
            shape = androidx.compose.foundation.shape.CircleShape,
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer
            )
        ) {
            Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = profile.fullName.firstOrNull()?.uppercase() ?: "S",
                    style = MaterialTheme.typography.displayMedium,
                    color = MaterialTheme.colorScheme.onPrimaryContainer
                )
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Text(
            profile.fullName,
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold
        )
        
        Text(
            profile.rollNumber,
            style = MaterialTheme.typography.titleMedium,
            color = MaterialTheme.colorScheme.primary
        )
        
        if (profile.faceRegistered) {
            Spacer(modifier = Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Default.VerifiedUser,
                    contentDescription = null,
                    tint = PresentColor,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text(
                    "Face Verified",
                    style = MaterialTheme.typography.bodySmall,
                    color = PresentColor
                )
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        // Profile Details
        Card(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(16.dp)) {
                ProfileItem(
                    icon = Icons.Default.Email,
                    label = "Email",
                    value = profile.email
                )
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                ProfileItem(
                    icon = Icons.Default.Phone,
                    label = "Phone",
                    value = profile.phone ?: "Not set"
                )
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                ProfileItem(
                    icon = Icons.Default.Business,
                    label = "Department",
                    value = profile.departmentName ?: "Not set"
                )
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                ProfileItem(
                    icon = Icons.Default.School,
                    label = "Semester",
                    value = "${profile.semester}"
                )
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                ProfileItem(
                    icon = Icons.Default.Group,
                    label = "Section",
                    value = profile.section ?: "Not set"
                )
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                ProfileItem(
                    icon = Icons.Default.CalendarMonth,
                    label = "Batch Year",
                    value = profile.batchYear?.toString() ?: "Not set"
                )
            }
        }
    }
}

@Composable
private fun ProfileItem(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    value: String
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth()
    ) {
        Icon(
            icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier.size(24.dp)
        )
        Spacer(modifier = Modifier.width(16.dp))
        Column {
            Text(
                label,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                value,
                style = MaterialTheme.typography.bodyLarge
            )
        }
    }
}
