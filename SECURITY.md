# Security Policy

The bbPress project takes vulnerabilities that could affect WordPress sites and
their communities seriously.

## Report vulnerabilities privately

**Do not report a suspected vulnerability in GitHub, Trac, the bbPress support
forums, WordPress.org support, Slack, or another public channel.**

Submit the report through the
[WordPress HackerOne program](https://hackerone.com/wordpress). Review the
[WordPress reporting guidance](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/)
before submitting. If that channel cannot accept the plugin report, follow the
[WordPress.org Plugin Team’s private reporting instructions](https://developer.wordpress.org/plugins/wordpress-org/reporting-plugin-security-issues/).

Do not test against bbPress.org, WordPress.org, or a site you do not own and
have explicit permission to test.

## What to include

Provide as much of the following as is safe:

- affected bbPress and WordPress versions;
- required user role, capability, configuration, or multisite state;
- security impact and who can be affected;
- minimal reproduction steps using synthetic data on an authorized site;
- relevant requests, responses, logs, or screenshots with credentials and
  personal data removed;
- any known mitigation or proposed patch;
- your preferred attribution, or a request to remain anonymous.

Do not put secrets, personal information, production data, or unnecessary
exploit detail in the report.

## Supported versions

Security fixes are normally released for the latest stable bbPress line. The
current development branch receives fixes before a coordinated release when
appropriate. Older releases may be affected and should be upgraded to the
latest stable version.

| Version | Security support |
| --- | --- |
| Latest stable release | Supported |
| Current development branch | Best effort; not recommended for production |
| Older releases | Upgrade to the latest stable release |

Exact affected versions and remediation guidance are determined during private
triage. Do not infer that an unreleased fix or a public source commit completes
a coordinated security release.

## Responsible research

Good-faith research must use systems and data you own or are authorized to use,
avoid disruption and privacy violations, stop after demonstrating the minimum
impact necessary, and allow the project a reasonable opportunity to investigate
and release a fix before disclosure.

This project does not promise a bounty, a particular response time, or safe
harbor beyond the terms of the reporting program you use.

