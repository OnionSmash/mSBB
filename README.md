# mSBB: macOS Security Baseline Builder

<p align="center">
  <img src="https://img.shields.io/badge/macOS-13%2B-blue" alt="macOS 13+">
  <img src="https://img.shields.io/badge/Swift-5.9-orange" alt="Swift 5.9">
  <img src="https://img.shields.io/badge/SwiftUI-Native-green" alt="SwiftUI">
</p>

**mSBB** is a native macOS application that provides an intuitive graphical interface for creating, customizing, and managing security compliance baselines. Built on top of the [macOS Security Compliance Project (mSCP)](https://github.com/usnistgov/macos_security), it makes enterprise-grade security configuration accessible to system administrators, security professionals, and compliance officers.

---

## 🌟 Key Features

### 📋 **Comprehensive Baseline Management**
- Browse 20+ pre-configured security baselines
- Support for NIST 800-53, CIS Benchmarks, DISA STIG, and CMMC frameworks
- Create custom baselines tailored to your organization
- Multi-version support (macOS 13 Ventura through macOS 26 Tahoe)

### 🔍 **Intelligent Rule Exploration**
- Explore hundreds of security rules with detailed documentation
- Understand the "what" and "why" behind each control
- View check and remediation scripts
- See compliance framework mappings

### ✏️ **Interactive Baseline Editor**
- Toggle security rules on/off with intuitive switches
- Real-time search and filtering
- Visual statistics (enabled/disabled counts)
- Unsaved changes tracking

### ✅ **Compliance Validation**
- Run compliance checks on your local Mac
- Real-time progress tracking
- Detailed pass/fail reporting with color-coded results
- Export reports as PDF.

### 📦 **Multi-Format Export**
- Generate compliance check scripts (bash)
- Create configuration profiles (mobileconfig)
- Export PDF documentation

### 🔄 **Automatic GitHub Updates**
- Auto-check for baseline updates from mSCP repository
- See detailed changelogs (added/removed/modified rules)
- One-click update application
- Configurable update preferences

### 🎨 **Modern macOS Interface**
- Native SwiftUI design
- Light, dark, and auto themes
- Smooth animations and transitions

---

## 📸 Screenshots

> *Note: Add screenshots here showing:*
> - *Main baseline list view*
> - *Baseline workspace with three-pane layout*
> - *Compliance check results*
> - *Export options*
> - *Update check view*

---

## 🚀 Getting Started

### Installation

#### Pre-Built Release

1. Download the latest release from [Releases](https://github.com/yourusername/mSCP-GUI/releases)
2. Open the downloaded `.dmg` file
3. Drag **mSBB** to your Applications folder
4. Launch from Applications (right-click → Open first time to bypass Gatekeeper)


### First Launch

1. **Launch the app** from Applications
2. **Browse baselines** - Start with pre-configured baselines like "NIST 800-53r5 Moderate" or "CIS Level 1"
3. **Select a baseline** - Click to open the workspace view
4. **Explore rules** - Review the security controls included
5. **Customize** (optional) - Edit rules to match your requirements
6. **Run compliance check** - Test against your Mac (requires admin password)
7. **Export** - Generate scripts or configuration profiles

---

## 📖 Usage Guide

### Creating a Custom Baseline

1. Click **"New Baseline"** in the toolbar
2. Enter baseline details:
   - **Name:** Internal identifier (e.g., `company_secure`)
   - **Title:** Display name (e.g., "Company Secure Configuration")
   - **Description:** Brief explanation
   - **macOS Version:** Target OS version
3. Click **"Create"**
4. In the workspace, click **"Edit Rules"**
5. Toggle rules on/off as needed
6. Click **"Save"**

### Running a Compliance Check

1. Select a baseline from the list
2. Navigate to **"Compliance"** tab
3. Click **"Run Check"**
4. Enter admin password when prompted (required for system checks)
5. Wait for checks to complete (progress shown)
6. Review results:
   - 🟢 **Green:** Compliant
   - 🔴 **Red:** Non-compliant
   - ⚪️ **Gray:** Not applicable
   - 🟡 **Yellow:** Error during check
7. Export report if needed

### Exporting a Baseline

1. Select a baseline from the list
2. Navigate to **"Export"** tab
3. Choose export types:
   - ☑️ Shell Script - Compliance check script
   - ☑️ Configuration Profile - mobileconfig files
   - ☑️ PDF Report - Documentation
4. Click **"Export Selected Files"**
5. Choose save location
6. Files generated and saved

### Checking for Updates

**Automatic (Default):**
- App checks for updates on launch
- Update badge appears in toolbar if updates available
- Click badge to view and apply updates

**Manual:**
1. Click toolbar menu (⋯)
2. Select **"Check for Updates"**
3. View available updates with change details
4. Click **"Apply All Updates"** to download and apply

**Configure Preferences:**
1. Click toolbar menu (⋯)
2. Select **"Update Preferences..."**
3. Adjust settings:
   - ✅ Enable/disable auto-check
   - ⏱️ Check frequency (6-168 hours)
   - 🚀 Check on launch
   - 🍎 Tracked macOS versions

---

## 📚 Resources

### Related Projects

- [macOS Security Compliance Project (mSCP)](https://github.com/usnistgov/macos_security) - Official NIST project with baseline YAML files
- [mSCP GitHub Repository](https://github.com/usnistgov/macos_security) - Source baselines and rules
- [NIST 800-53](https://csrc.nist.gov/publications/detail/sp/800-53/rev-5/final) - Security controls standard
- [CIS Benchmarks](https://www.cisecurity.org/cis-benchmarks/) - Consensus-based security guides

---

## 🔒 Security



### Sudo Privileges

mSCP-GUI requires administrator privileges to run compliance checks because many security settings require elevated access to inspect. The app:

- ✅ Only requests sudo when running checks
- ✅ Clearly indicates which operations need elevation
- ✅ Never stores passwords
- ✅ Uses macOS Authorization Services APIs
- ✅ Sandboxes script execution

### Data Privacy

- ✅ No telemetry or analytics collected
- ✅ No network requests except to public GitHub repository
- ✅ All data stored locally on your Mac
- ✅ No third-party tracking

---

## 🙏 Acknowledgments

- **NIST macOS Security Compliance Project** - For creating and maintaining the comprehensive security baselines
- **Yams** - For the excellent YAML parsing library
- **Apple** - For SwiftUI and macOS frameworks
- **Contributors** - Everyone who has contributed and tested the application

---

## 📊 Project Status

- ✅ **Core Features:** Complete
- ✅ **Update System:** Complete
- ✅ **Multi-Version Support:** Complete (macOS 13-26)
- ✅ **Export System:** Complete
- ✅ **Compliance Checking:** Complete
- 🚧 **Intune Connection:** Planned
- 🚧 **Import an exported Baseline:** Planned

---

## 🗒️ Backlog

 - Better customization and visuals for PDF Exports
 - Reduce loadingtime when opening a workspace
 - Better Check Compliance and result handling
 - Cleanup in the interface
 - When mSCP v2 is official the transition will be made for the application
 

## 💬 Support

### FAQ

**Q: Do I need an internet connection?**  
A: No, the app works offline. Internet is only needed for GitHub updates.

**Q: Can I use this on multiple Macs?**  
A: Yes! Export your custom baselines and import them on other Macs.

**Q: Does this modify my system settings?**  
A: No, mSBB only *checks* compliance. It provides fix scripts but doesn't apply them automatically.

**Q: What's the difference between bundled and custom baselines?**  
A: Bundled baselines come from NIST mSCP (read-only). Custom baselines are created by you (editable).

**Q: How often are baselines updated?**  
A: NIST mSCP updates baselines periodically. Enable auto-update to stay current.

---

<p align="center">
  Made with ❤️ for macOS security professionals
</p>