# Contributing to Hellō Login

This plugin provides security enhancements to WordPress, and your help making it even more awesome will be greatly appreciated :)

There are many ways to contribute to the project!

- [Translating strings into your language](TBD).
- Answering open questions under the GitHub Issue Tracker (https://github.com/hellocoop/wordpress/issues).
- Testing open [issues](https://github.com/hellocoop/wordpress/issues) or [pull requests](https://github.com/hellocoop/wordpress/pulls) and sharing your findings in a comment.
- Submitting fixes, improvements, and enhancements.
- Disclose a security issue to our team.

If you wish to contribute code, please read the information in the sections below. Then [fork](https://help.github.com/articles/fork-a-repo/) the plugin, commit your changes, and [submit a pull request](https://help.github.com/articles/using-pull-requests/) 🎉

We use the `good first issue` label to mark issues that are suitable for new contributors. You can find all the issues with this label [here](https://github.com/hellocoop/wordpress/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22).

Hellō Login is licensed under the GPLv2.0, and all contributions to the project will be released under the same license. You maintain copyright over any contribution you make, and by submitting a pull request, you are agreeing to release that contribution under the GPLv2.0 license.

## Getting started

- [How to set up the plugin development environment](https://github.com/oidc-wp/openid-connect-generic/wiki/How-to-setup-the-plugin-development-environment)

## Coding Guidelines and Development 🛠

- Ensure you stick to the [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/)
- Run our build process described in the document on [How to setup the plugin development environment](https://github.com/oidc-wp/openid-connect-generic/wiki/How-to-setup-the-plugin-development-environment), it will install everything needed to do development on our plugin.
- Whenever possible please fix pre-existing code standards errors in the files that you change. It is ok to skip that for larger files or complex fixes.
- Ensure you use LF line endings in your code editor. Use [EditorConfig](http://editorconfig.org/) if your editor supports it so that indentation, line endings and other settings are auto configured.
- When committing, reference your issue number (#1234) and include a note about the fix.
- Ensure that your code supports the minimum supported versions of PHP and WordPress; this is shown at the top of the `readme.txt` file.
- Push the changes to your fork and submit a pull request on the `dev` branch of the plugin repository.
- Make sure to write good and detailed commit messages (see [this post](https://chris.beams.io/posts/git-commit/) for more on this) and follow all the applicable sections of the pull request template.
- Please avoid modifying the changelog directly or updating the .pot files. These will be updated by the plugin team.

## Feature Requests 🚀

Feature requests can be [submitted to our issue tracker](https://github.com/hellocoop/wordpress/issues/new?template=5-Feature-request.md). Be sure to include a description of the expected behavior and use case, and before submitting a request, please search for similar ones in the closed issues.
