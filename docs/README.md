# HAMS - Humanitarian Aid Management System

## Quick Start Guide for Somali NGOs

### System Overview
HAMS is a simple web-based system to help Somali humanitarian organizations manage aid distribution more effectively. It tracks recipients, supplies, deliveries, and projects while keeping everything organized and transparent.

### Getting Started

#### 1. Login
- Open your web browser and go to your HAMS website
- Use your username and password to login
- **Demo credentials:** Username: `admin`, Password: `admin123`

#### 2. Dashboard
After login, you'll see the main dashboard with:
- Quick statistics (recipients, deliveries, supplies, projects)
- Stock alerts for low inventory
- Recent activity
- Quick action buttons

### Main Features

#### Aid Recipients (People Supported)
**Purpose:** Register and manage people who receive aid

**How to use:**
1. Click "Aid Recipients" in the sidebar
2. Click "Register New Recipient" 
3. Fill in required information:
   - Recipient ID (unique identifier like SOM2024001)
   - Full name
   - Location and district
   - Household size
   - Displacement status (Resident, IDP, Refugee, Returnee)
   - Vulnerability level (Low, Medium, High, Critical)
4. Save the recipient

**Search and filter:** Use the search box to find recipients by name, ID, or location

#### Supplies (Inventory Management)
**Purpose:** Track what items you have in stock

**How to use:**
1. Click "Supplies" in the sidebar
2. Click "Add New Item"
3. Enter item details:
   - Item name (e.g., Rice, Cooking Oil)
   - Category (Food, Water, Hygiene, Medical, etc.)
   - Current stock amount
   - Minimum stock level (for alerts)
   - Unit type (kg, liters, pieces)
   - Warehouse location
4. Save the item

**Stock alerts:** The system will warn you when items are running low

**Adjust stock:** Click "Adjust" next to any item to add or remove stock

#### Aid Delivery (Distribution Records)
**Purpose:** Record when you give supplies to recipients

**How to use:**
1. Click "Aid Delivery" in the sidebar
2. Click "Record New Delivery"
3. Fill in delivery details:
   - Delivery date
   - Select recipient from dropdown
   - Select supply item (shows available stock)
   - Enter quantity delivered
   - Delivery location
   - Link to project (optional)
   - Mark if receipt was signed
4. Save the delivery

**Important:** Stock levels update automatically when you record deliveries

#### Projects (Relief Programs)
**Purpose:** Organize aid activities by donor-funded projects

**How to use:**
1. Click "Projects" in the sidebar
2. Click "Create New Project"
3. Enter project information:
   - Project name
   - Project code (optional)
   - Donor organization
   - Target location and beneficiaries
   - Start and end dates
   - Budget
   - Status (Planning, Active, Completed, Suspended)
4. Save the project

**Link deliveries:** When recording deliveries, you can link them to specific projects

#### Reports
**Purpose:** Generate summaries for donors and management

**Available reports:**
- **Summary Report:** Overall statistics and trends
- **Deliveries Report:** Detailed list of all distributions
- **Recipients Report:** Information about all registered people
- **Supplies Report:** Current inventory status
- **Projects Report:** Progress on all projects

**How to generate:**
1. Click "Reports" in the sidebar
2. Select report type
3. Choose date range
4. Filter by project if needed
5. Click "Generate"
6. Export to CSV or print as needed

#### Activity Records
**Purpose:** Track who did what and when (for accountability)

**What it shows:**
- User logins and logouts
- When records were created, updated, or deleted
- Delivery activities
- Report generation

### Mobile Use Tips

HAMS is designed for mobile phones and tablets:
- Use the menu button (☰) to open navigation on small screens
- Forms are optimized for touch input
- Tables scroll horizontally on narrow screens
- All features work offline once loaded

### Data Security

**Important reminders:**
- Only collect essential personal information
- Log out when finished using the system
- Keep login credentials secure
- Regular backups are recommended

### Common Tasks

#### Daily Operations
1. **Check stock alerts** on dashboard
2. **Record deliveries** as they happen
3. **Register new recipients** when needed
4. **Adjust stock** when supplies arrive

#### Weekly Tasks
1. **Review activity records** for accountability
2. **Generate delivery reports** for management
3. **Update project status** as needed

#### Monthly Tasks
1. **Generate summary reports** for donors
2. **Export recipient data** for backup
3. **Review and update supply minimum levels**

### Troubleshooting

**Can't login?**
- Check username and password spelling
- Contact system administrator

**Stock shows wrong amount?**
- Use "Adjust Stock" to correct levels
- Check recent delivery records

**Can't find a recipient?**
- Use search function with partial names
- Check spelling of name or ID

**Report looks empty?**
- Check date range filters
- Ensure data exists for selected period

### Getting Help

**For technical issues:**
- Contact your system administrator
- Check the Activity Records to see recent changes
- Try logging out and back in

**For training:**
- This guide covers basic operations
- Practice with test data first
- Ask experienced users for help

### System Information

**Built for Somalia context:**
- Works with low bandwidth internet
- Mobile-first design for field workers
- Simple English terminology
- Supports IDP and refugee tracking
- Designed for local NGO workflows

**Technical details:**
- Runs on standard web servers
- Uses MySQL database
- Automatic backups recommended
- Regular updates available

---

**Remember:** HAMS is designed to be simple and reliable. Focus on accurate data entry and regular use rather than complex features. The system will help you serve more people more effectively while maintaining transparency and accountability.

For questions or support, contact your system administrator or technical support team.
