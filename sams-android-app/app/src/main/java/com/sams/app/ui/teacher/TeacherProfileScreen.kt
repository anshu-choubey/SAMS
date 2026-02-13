package com.sams.app.ui.teacher

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.MenuBook
import androidx.compose.material.icons.automirrored.outlined.Help
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.automirrored.outlined.MenuBook
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TeacherProfileScreen(
    viewModel: TeacherViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onLogout: () -> Unit
) {
    val uiState by viewModel.dashboardState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadDashboard()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Profile", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
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
            is TeacherUiState.Loading -> LoadingScreen()
            is TeacherUiState.Error -> ErrorScreen(
                message = state.message,
                onRetry = { viewModel.loadDashboard() }
            )
            is TeacherUiState.Success -> {
                val data = state.data as TeacherDashboardData
                ProfileContent(
                    profile = data.profile,
                    subjects = data.subjects,
                    totalStudents = data.totalStudents,
                    modifier = Modifier.padding(padding),
                    onLogout = onLogout
                )
            }
            else -> {}
        }
    }
}

@Composable
private fun ProfileContent(
    profile: TeacherProfile,
    subjects: List<TeacherSubject>,
    totalStudents: Int,
    modifier: Modifier = Modifier,
    onLogout: () -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(0.dp)
    ) {
        // Header
        item {
            TeacherProfileHeader(profile = profile)
        }
        
        // Stats
        item {
            StatsSection(
                subjectCount = subjects.size,
                studentCount = totalStudents
            )
        }
        
        // Personal Info
        item {
            SectionHeader(
                title = "Personal Information",
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)
            )
        }
        
        item {
            PersonalInfoCard(profile = profile)
        }
        
        // Assigned Subjects
        if (subjects.isNotEmpty()) {
            item {
                SectionHeader(
                    title = "Assigned Subjects",
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)
                )
            }
            
            items(subjects) { subject ->
                SubjectInfoCard(
                    subject = subject,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 4.dp)
                )
            }
        }
        
        // Account Actions
        item {
            SectionHeader(
                title = "Account",
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)
            )
        }
        
        item {
            AccountActionsCard(onLogout = onLogout)
        }
        
        item { Spacer(modifier = Modifier.height(32.dp)) }
    }
}

@Composable
private fun TeacherProfileHeader(profile: TeacherProfile) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                brush = Brush.verticalGradient(
                    colors = listOf(SecondaryTeal, PrimaryBlue)
                )
            )
            .padding(vertical = 40.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Box(
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = profile.fullName.firstOrNull()?.uppercase() ?: "T",
                    style = MaterialTheme.typography.displayMedium,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = profile.fullName,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = Color.White,
                textAlign = TextAlign.Center
            )
            
            profile.designation?.let { designation ->
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = designation,
                    style = MaterialTheme.typography.bodyLarge,
                    color = Color.White.copy(alpha = 0.9f)
                )
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = Color.White.copy(alpha = 0.2f)
            ) {
                Text(
                    text = "Teacher ID: ${profile.employeeId ?: "N/A"}",
                    style = MaterialTheme.typography.labelMedium,
                    color = Color.White,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp)
                )
            }
        }
    }
}

@Composable
private fun StatsSection(
    subjectCount: Int,
    studentCount: Int
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        StatCard(
            title = "Subjects",
            value = subjectCount.toString(),
            icon = Icons.AutoMirrored.Outlined.MenuBook,
            iconTint = SecondaryTeal,
            containerColor = SecondaryTealContainer.copy(alpha = 0.5f),
            modifier = Modifier.weight(1f)
        )
        StatCard(
            title = "Students",
            value = studentCount.toString(),
            icon = Icons.Outlined.People,
            iconTint = AccentPurple,
            containerColor = AccentPurpleContainer.copy(alpha = 0.5f),
            modifier = Modifier.weight(1f)
        )
    }
}

@Composable
private fun PersonalInfoCard(profile: TeacherProfile) {
    SAMSCard(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            InfoItem(
                icon = Icons.Outlined.Person,
                label = "Full Name",
                value = profile.fullName
            )
            
            HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
            
            InfoItem(
                icon = Icons.Outlined.Email,
                label = "Email",
                value = profile.email
            )
            
            HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
            
            InfoItem(
                icon = Icons.Outlined.Badge,
                label = "Employee ID",
                value = profile.employeeId ?: "N/A"
            )
            
            profile.departmentName?.let { dept ->
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                InfoItem(
                    icon = Icons.Outlined.Business,
                    label = "Department",
                    value = dept
                )
            }
            
            profile.designation?.let { desig ->
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                InfoItem(
                    icon = Icons.Outlined.WorkOutline,
                    label = "Designation",
                    value = desig
                )
            }
            
            profile.phone?.let { phone ->
                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                InfoItem(
                    icon = Icons.Outlined.Phone,
                    label = "Phone",
                    value = phone
                )
            }
        }
    }
}

@Composable
private fun InfoItem(
    icon: ImageVector,
    label: String,
    value: String
) {
    Row(
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(40.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(MaterialTheme.colorScheme.surfaceVariant),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(20.dp)
            )
        }
        
        Spacer(modifier = Modifier.width(16.dp))
        
        Column {
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

@Composable
private fun SubjectInfoCard(
    subject: TeacherSubject,
    modifier: Modifier = Modifier
) {
    SAMSCard(modifier = modifier.fillMaxWidth()) {
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
                    imageVector = Icons.AutoMirrored.Filled.MenuBook,
                    contentDescription = null,
                    tint = PrimaryBlue
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = subject.subjectCode,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = subject.subjectName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium
                )
                Text(
                    text = "Semester ${subject.semester} • ${subject.section}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

@Composable
private fun AccountActionsCard(onLogout: () -> Unit) {
    SAMSCard(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp)
    ) {
        Column {
            AccountActionItem(
                icon = Icons.Outlined.Settings,
                title = "Settings",
                subtitle = "App preferences",
                onClick = { }
            )
            
            HorizontalDivider()
            
            AccountActionItem(
                icon = Icons.AutoMirrored.Outlined.Help,
                title = "Help & Support",
                subtitle = "Get help or report issues",
                onClick = { }
            )
            
            HorizontalDivider()
            
            AccountActionItem(
                icon = Icons.Outlined.Info,
                title = "About",
                subtitle = "Version 1.0.0",
                onClick = { }
            )
            
            HorizontalDivider()
            
            AccountActionItem(
                icon = Icons.AutoMirrored.Outlined.Logout,
                title = "Logout",
                subtitle = "Sign out of your account",
                onClick = onLogout,
                tint = ErrorRed
            )
        }
    }
}

@Composable
private fun AccountActionItem(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit,
    tint: Color = MaterialTheme.colorScheme.onSurface
) {
    Surface(
        onClick = onClick,
        color = Color.Transparent
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = tint,
                modifier = Modifier.size(24.dp)
            )
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.Medium,
                    color = tint
                )
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(20.dp)
            )
        }
    }
}
