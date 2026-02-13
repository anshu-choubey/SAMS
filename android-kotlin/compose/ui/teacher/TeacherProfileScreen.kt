package com.sams.app.ui.teacher

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
import com.sams.app.data.models.TeacherProfile

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TeacherProfileScreen(
    viewModel: TeacherViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    val dashboardState by viewModel.dashboardState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadDashboard()
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
        when (val state = dashboardState) {
            is TeacherUiState.Loading -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
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
            is TeacherUiState.Success -> {
                val profile = (state.data as? com.sams.app.data.models.TeacherDashboardData)?.profile
                if (profile == null) {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text("Profile not available")
                    }
                } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
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
                            text = profile.fullName.firstOrNull()?.uppercase() ?: "T",
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
                    profile.employeeId ?: "Teacher",
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.primary
                )
                
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
                            icon = Icons.Default.Work,
                            label = "Designation",
                            value = profile.designation ?: "Not set"
                        )
                        HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                        ProfileItem(
                            icon = Icons.Default.School,
                            label = "Qualification",
                            value = profile.qualification ?: "Not set"
                        )
                    }
                }
            }
                }
            }
            else -> {}
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
