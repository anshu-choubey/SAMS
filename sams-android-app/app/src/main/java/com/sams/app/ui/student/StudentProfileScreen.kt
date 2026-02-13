package com.sams.app.ui.student

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*

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
                title = { Text("My Profile", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.Transparent
                )
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is StudentUiState.Loading -> LoadingScreen()
            is StudentUiState.Error -> ErrorScreen(
                message = state.message,
                onRetry = { viewModel.loadProfile() }
            )
            is StudentUiState.Success -> {
                val profile = state.data as StudentProfileFull
                ProfileContent(
                    profile = profile,
                    modifier = Modifier.padding(padding)
                )
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
    ) {
        // Header with gradient
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(PrimaryBlue, AccentPurple)
                    )
                )
                .padding(top = 16.dp, bottom = 48.dp),
            contentAlignment = Alignment.Center
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                // Avatar
                Box(
                    modifier = Modifier
                        .size(100.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.2f)),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = profile.fullName.firstOrNull()?.uppercase() ?: "S",
                        style = MaterialTheme.typography.displaySmall,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                Text(
                    text = profile.fullName,
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                
                Text(
                    text = profile.rollNumber,
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f)
                )
                
                // Face Registration Status
                if (profile.faceRegistered) {
                    Row(
                        modifier = Modifier.padding(top = 8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.VerifiedUser,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = SuccessGreen
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "Face Verified",
                            style = MaterialTheme.typography.labelMedium,
                            color = SuccessGreen
                        )
                    }
                }
            }
        }
        
        // Profile Details Card
        SAMSCard(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp)
                .offset(y = (-32).dp)
        ) {
            Column(modifier = Modifier.padding(20.dp)) {
                Text(
                    text = "Personal Information",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                ProfileInfoRow(
                    icon = Icons.Outlined.Email,
                    label = "Email",
                    value = profile.email
                )
                
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                
                ProfileInfoRow(
                    icon = Icons.Outlined.Phone,
                    label = "Phone",
                    value = profile.phone ?: "Not provided"
                )
                
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                
                ProfileInfoRow(
                    icon = Icons.Outlined.Business,
                    label = "Department",
                    value = profile.departmentName ?: "N/A"
                )
                
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                
                ProfileInfoRow(
                    icon = Icons.Outlined.School,
                    label = "Semester",
                    value = "Semester ${profile.semester}"
                )
                
                profile.section?.let {
                    HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                    ProfileInfoRow(
                        icon = Icons.Outlined.Groups,
                        label = "Section",
                        value = it
                    )
                }
                
                profile.batchYear?.let {
                    HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                    ProfileInfoRow(
                        icon = Icons.Outlined.CalendarMonth,
                        label = "Batch Year",
                        value = it.toString()
                    )
                }
            }
        }
        
        // Face Registration Status Card - only show when face is NOT registered
        if (!profile.faceRegistered) {
            SAMSCard(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
                    .offset(y = (-16).dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Face Recognition",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(56.dp)
                                .clip(RoundedCornerShape(12.dp))
                                .background(WarningOrangeContainer),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Outlined.Face,
                                contentDescription = null,
                                tint = WarningOrange,
                                modifier = Modifier.size(28.dp)
                            )
                        }
                        
                        Spacer(modifier = Modifier.width(16.dp))
                        
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "Face Not Registered",
                                style = MaterialTheme.typography.titleSmall,
                                fontWeight = FontWeight.SemiBold
                            )
                            Text(
                                text = "Please register your face for attendance",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                        
                        StatusBadge(status = "Pending")
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
    }
}

@Composable
private fun ProfileInfoRow(
    icon: ImageVector,
    label: String,
    value: String
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier.size(24.dp)
        )
        
        Spacer(modifier = Modifier.width(16.dp))
        
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                text = value,
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.Medium
            )
        }
    }
}
