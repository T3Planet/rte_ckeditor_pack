.. include:: ../Includes.txt

.. _migrationtov3x:

=========================
Migration v3.x
=========================

To ensure a smooth and stable upgrade to **v3.x**, please review the following notes before proceeding.

Recommended: Fresh Installation
-------------------------------

For optimal performance and to prevent conflicts with older configurations, **please consider reinstalling the entire extension**.

A fresh installation ensures that all updated features, revised YAML structures and configuration changes are applied correctly.

Steps to Follow
---------------

1. Uninstall the existing extension from the Extension Manager.
2. Clear all TYPO3 caches.
3. Install the latest version (**v3.x**) from TER or via Composer.
4. Verify the RTE setup in both backend and frontend.
5. Re-Configure any custom YAML settings using the updated schema included in v3.x.

Why This Is Required
--------------------

Version **3.x** introduces important structural updates, new features and revised configuration logic.

A clean installation helps ensure:

- No outdated YAML files conflict with the new configuration.
- No duplicated or legacy settings remain.
- New default features load correctly.
- Overall smoother and more predictable behavior.
