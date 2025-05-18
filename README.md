# Tournament Registration Manager

A comprehensive WordPress plugin for managing tournament registrations, supporting both single-player and team-based competitions.

## Features

### Tournament Management
- Create and manage tournaments with customizable settings
- Set tournament capacity (maximum teams/players)
- Define tournament start and end dates
- Support for both team and single-player tournaments
- Tournament status management (active, inactive, pending)

### Team Management
- Create and manage teams
- Team leader functionality
- Automatic invitation link generation
- Team join request system
- Team member approval/rejection workflow
- Team capacity management

### Registration System
- Single-player registration
- Team registration
- Gamer tag management
- Registration validation
- Capacity control
- Registration status tracking

### Admin Features
- Comprehensive admin dashboard
- Tournament management interface
- Team management interface
- Registration overview
- Email notification settings
- Customizable email templates
- System settings configuration

### User Features
- User-friendly registration forms
- Team creation and management
- Join team requests
- Registration status tracking
- Email notifications

## Installation

1. Download the plugin files
2. Upload the plugin folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings under 'Tournament Registration' in the admin menu

## Configuration

### General Settings
1. Navigate to Tournament Registration > Settings
2. Configure email notification settings
3. Set default registration type
4. Define maximum teams per user
5. Set maximum players per team
6. Select registration and team management pages

### Email Templates
1. Go to Tournament Registration > Settings
2. Customize email templates for:
   - Join requests
   - Request approvals
   - Request rejections
3. Available variables:
   - {team_name}
   - {player_name}
   - {tournament_name}
   - {invitation_link}

## Usage

### Creating a Tournament
1. Go to Tournament Registration > Add New Tournament
2. Fill in tournament details:
   - Title
   - Description
   - Registration type
   - Maximum teams/players
   - Start and end dates
   - Status
3. Save the tournament

### Team Registration
1. Create a team or join an existing team
2. Team leader can:
   - Manage team members
   - Approve/reject join requests
   - Generate invitation links
3. Team members can:
   - View team status
   - Update their gamer tag
   - Leave the team

### Single Player Registration
1. Navigate to the tournament registration page
2. Fill in your gamer tag
3. Submit registration

## Shortcodes

### Tournament Registration Form
```
[tournament_registration id="tournament_id"]
```

### Team Management
```
[team_management]
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher

## Security

- Nonce verification for all forms
- User capability checks
- Data sanitization and validation
- Secure database queries
- XSS protection

## Support

For support, please:
1. Check the documentation
2. Visit our support forum
3. Contact our support team

## Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Your Name/Company] 