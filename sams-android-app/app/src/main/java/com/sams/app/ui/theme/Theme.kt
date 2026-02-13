package com.sams.app.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val LightColorScheme = lightColorScheme(
    primary = PrimaryBlue,
    onPrimary = TextOnPrimary,
    primaryContainer = PrimaryBlueContainer,
    onPrimaryContainer = PrimaryBlueDark,
    
    secondary = SecondaryTeal,
    onSecondary = TextOnPrimary,
    secondaryContainer = SecondaryTealContainer,
    onSecondaryContainer = SecondaryTealDark,
    
    tertiary = AccentPurple,
    onTertiary = TextOnPrimary,
    tertiaryContainer = AccentPurpleContainer,
    onTertiaryContainer = AccentPurple,
    
    background = BackgroundLight,
    onBackground = TextPrimary,
    
    surface = SurfaceLight,
    onSurface = TextPrimary,
    surfaceVariant = SurfaceVariantLight,
    onSurfaceVariant = TextSecondary,
    
    error = ErrorRed,
    onError = TextOnPrimary,
    errorContainer = ErrorRedContainer,
    onErrorContainer = ErrorRed,
    
    outline = CardBorder,
    outlineVariant = Divider
)

private val DarkColorScheme = darkColorScheme(
    primary = PrimaryBlueLight,
    onPrimary = TextPrimary,
    primaryContainer = PrimaryBlueDark,
    onPrimaryContainer = PrimaryBlueContainer,
    
    secondary = SecondaryTealLight,
    onSecondary = TextPrimary,
    secondaryContainer = SecondaryTealDark,
    onSecondaryContainer = SecondaryTealContainer,
    
    tertiary = AccentPurpleLight,
    onTertiary = TextPrimary,
    tertiaryContainer = AccentPurple,
    onTertiaryContainer = AccentPurpleContainer,
    
    background = BackgroundDark,
    onBackground = TextPrimaryDark,
    
    surface = SurfaceDark,
    onSurface = TextPrimaryDark,
    surfaceVariant = SurfaceVariantDark,
    onSurfaceVariant = TextSecondaryDark,
    
    error = ErrorRedLight,
    onError = TextPrimary,
    errorContainer = ErrorRed,
    onErrorContainer = ErrorRedContainer,
    
    outline = SurfaceVariantDark,
    outlineVariant = SurfaceVariantDark
)

@Composable
fun SAMSTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = false,
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalContext.current
            if (darkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        }
        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }
    
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = colorScheme.background.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = AppTypography,
        content = content
    )
}
