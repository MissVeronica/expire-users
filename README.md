# UM - Expire User Roles - Version 2.0.0 BETA
 Extension to Ultimate Member for User Roles Expiration based on an updated version of the [Expire Users](https://github.com/benhuson/expire-users) plugin.

Support for:
* User Role selections to be included each with own settings 
* UM Registration forms option
* UM Login form option for existing or first User logins or renewal with each login.
* UM Email templates
* UM Email and new placeholders
* User Account page status display and settings
* UM Dashboard User status info
* WP All Users filter and listings of User Role statuses
* Shortcodes

## 1. Settings
### 1.1 Wordpress All Users -> Expire settings
*  Plugin activation to enable the page settings and UM Role settings
*  Expiry Date - Select your time period in number of days, weeks, months or years
*  On Expire, Default to Role - Select your User Role for hosting of expired User Roles

### 1.2 UM User Roles -> edit an User Role
Roles with Admin capabilities are excluded and the "On Expire, Default to Role" set by the "Expire Users# plugin
* Include this Role in User Role Expiration? - Activate the "Expire Users" plugin's UM integration for this User Role.
* Include Users during Registration? - New Users registered are included. Avoid this option, use the Login option instead, if you have email address confirmation or Admin approval for new Users. No email is sent, include info in your welcome email.
* Include an existing User at their next/first login? - Existing Users with this Role and without a free period are included at their next/first login and an email login notification is sent if enabled.
* Update the Expiration date at each User login? - A new Free User Role Expiration period is started at each User login. No User email login notification is sent for this option.
* Send an User Reminder email before Expiration day? - If selected Users may also enable/disable this option at their Account page tab for User Role Expiration.
* User Reminder email number of days in advance? - Select the number of days between 1 and 14.
* Optional Admin email address? - If empty the default UM admin email address will be used.

### 1.3 UM Settings -> Emails -> Templates list
New template HTML sources are copied by the plugin to your active theme's local UM email templates folder.
* Expire Users - User Welcome = If template is active email is sent to user when included in User Role Expiration during login or backend renewal.
* Expire Users - User Reminder = If template is active an email is sent to the user as a Reminder of the upcoming User Role Expiration.
* Expire Users - User Role Expired = If template is active an email is sent to the User when the User Role is expired.
* Expire Users - Admin about User Role Expired = If template is active an email is sent to the Site Admin about an User Role being expired.
* Expire Users - Admin about User Role Renewal = If template is active an email is sent to the Site Admin about a renewal by an Account with Expired User Role.

## 2. Plugin functions
### 2.1 Placeholders
UM [email placeholders](https://docs.ultimatemember.com/article/1340-placeholders-for-email-templates) are valid. 

These placeholders are only valid for the 5 email templates used by this plugin. 

* {expiration-date} - User Role Expiration date and time
* {expiration-url} - URL to the Account page tab for status and update of User Role Epiration date
* {expiration-link} - Link to the Account page Expiration tab with the page button text: "Renew User Role %s" or "Update User Role"
* {expiration-reminder} - Reminder email sending date and time
* {expiration-reminder-days} - Number of days the Reminder email is sent in advance of User Role Expiration
* {expiration-role} - Expiration User Role name  
        
### 2.2 User Account Page
* Active User's [Account page](https://imgur.com/a/rvBNIop)
* Expired User's [Account page](https://imgur.com/a/UiCZJat)

### 2.3 UM Dashboard
Information at [UM Dashboard](https://imgur.com/a/OlT2w55) and all options [UM Dashboard](https://imgur.com/a/Gviz1mO)
* Next WP Cronjob scheduled at %s
* All User Expiration Roles
* %s Users are not expired - %s Users are not expired incl %d Users are pending to be expired
* %s Users are expired
* No Users are pending - %d Users are pending to be expired by the next WP Cronjob or WP All Users list
* %s Users may expire during next 24 hours
* %s Users may expire during next 7 days
* %s Reminder emails will be sent during the nect 24 hours

### 2.4 WP All Users
* Expire Date column sortable
* User dropdown per User not expired with action "Expire Now"
* User dropdown per User with action "Renew Now"
* Filter for listing "Users not expired" and "Users expired"

### 2.5 Shortcodes
Displays the expiry date for the current user.
* <code>[expire_users_current_user_expire_date]</code> used at the first line of the User Account page

Allowed Attributes:
- date_format
- expires_format
- expired_format
- never_expire

Displays the expiry time remaining for the current user.
* <code>[expire_users_current_user_expire_countdown]</code> used at the second line of the User Account page

Allowed Attributes:
- expires_format
- expired_format
- expired
- never_expire

Source file <code>.../plugins/expire-users-um-master/includes/shortcodes.php</code>

### 2.6 Cron job
* The "Expire Users" file cron.php is updated by "User Role Expiration" during activation to include calls required for the integration.

### 2.7 Translations & Text changes
* Available are local language files ( FR, NL, IT, BR ) for the original "Expire Users" plugin are downloaded and installed with the plugin
* [WP Translations](https://translate.wordpress.org/projects/wp-plugins/expire-users/)
* Plugin text domain - expire-users - for the "[Loco Translate](https://wordpress.org/plugins/loco-translate/)" plugin or the "[Say What?](https://wordpress.org/plugins/say-what/)" plugin 

### 2.8 Meta data
* <code>_expire_user_date</code> Timestamp for time to expire or when expired
* <code>_expire_user_expired</code> values Y or N ie expired or not expired
* <code>_expire_user_settings</code> Current settings array for this User
* <code>_expire_users_role</code> Main Role ID ie returning User Role at renewal
* <code>expire_users_reminder</code> The User setting at the Account page for a Reminder email yes/no
* <code>_expire_users_reminder</code> Timestamp for sending a Reminder email. Empty after Reminder email sent.

## 3. Updates
None

## 4. Plugin References
* [Expire Users](https://github.com/benhuson/expire-users)
* [Email Parse Shortcode](https://github.com/MissVeronica/um-email-parse-shortcode)
* [Additional email Recipients](https://github.com/MissVeronica/um-additional-email-recipients)
* [Index WP MySQL For Speed](https://wordpress.org/plugins/index-wp-mysql-for-speed/)

## 5. Installation and Updates
### 5.1 First install
* Download and install the "Expire User Roles" plugin ZIP file via the green "Code" button at this site
* Install as new Plugin, upload the "Expire User Roles" ZIP file in WordPress -> Plugins -> Add New -> Upload Plugin.
* Activate the "Expire User Roles" Plugin
### 5.2 Updates
* 
## 6. Support
* [Issues](https://github.com/MissVeronica/um-expire-user-roles/issues)
* [Discussions](https://github.com/MissVeronica/um-expire-user-roles/discussions)

