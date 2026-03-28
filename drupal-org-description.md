# SitePulse

**API-first monitoring for Drupal sites.**

SitePulse enhances Drupal's native monitoring capabilitis.

SitePulse transforms Drupal's status report into a powerful monitoring platform, providing structured JSON APIs that enable seamless integration with monitoring dashboards, fleet management tools, and automated workflows. Built with scalability in mind, SitePulse lays the groundwork for comprehensive Drupal site monitoring solutions.

## Key Features

- **Complete Status API**: Access all status report entries (PHP version, database info, module updates, security issues, etc.) via RESTful JSON endpoints
- **Severity Filtering**: Query only specific severity levels (ok, info, warning, error) for targeted monitoring
- **Health Summary Endpoint**: Get overall site health status and severity counts for uptime monitoring services
- **OpenAPI Specification**: Built-in API documentation accessible at `/api/sitepulse/v1/openapi.json`
- **Flexible Authentication**: Supports both cookie-based sessions and HTTP Basic Authentication
- **Configurable Caching**: Control data freshness with adjustable TTL settings
- **Privacy Controls**: Denylist sensitive status entries from API responses
- **Lightweight & Secure**: Minimal dependencies, follows Drupal security best practices

## Use Cases

- **Monitoring Dashboards**: Integrate site health data into Grafana, Nagios, or custom monitoring solutions
- **Fleet Management**: Monitor multiple Drupal sites from a centralized platform
- **Automated Auditing**: Build compliance and security monitoring workflows
- **DevOps Integration**: Include status checks in CI/CD pipelines and deployment scripts
- **Uptime Monitoring**: Quick health checks for load balancers and monitoring services
- **Platform Foundation**: Build advanced monitoring features and SaaS solutions on top of SitePulse APIs

## Quick Start

1. Install the module: `composer require drupal/sitepulse && drush en sitepulse`
2. Grant permissions at `/admin/people/permissions`
3. Configure settings at `/admin/config/system/sitepulse`
4. Query the API: `curl -u user:pass https://example-drupal.site/api/sitepulse/v1/status`

## API Endpoints

- `GET /api/sitepulse/v1/status` - Full status report
- `GET /api/sitepulse/v1/status?severity=error,warning` - Filtered by severity
- `GET /api/sitepulse/v1/status/summary` - Health summary for monitoring
- `GET /api/sitepulse/v1/openapi.json` - API documentation (public)

## Requirements

- Drupal 11.x
- PHP 8.3+
- Core modules: System, Basic Auth, User

## Support

- Report issues: [Issue queue](https://www.drupal.org/project/issues/sitepulse)
- Documentation: [Project page](https://www.drupal.org/project/sitepulse)
- Maintainers: [Shashikanth Palvatla](https://www.drupal.org/u/shashikanth171)