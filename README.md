# Wink Affiliate WordPress plugin documentation #

### Project information ###
Integrates WordPress with your Wink account at [https://studio.wink.travel](https://studio.wink.travel)

### Setup ###
Once you've created an account with Wink, you can install this WordPress plugin on your site. Once installed, the plugin will ask you for your clientId and secretKey to connect with your account on Wink. 

Once you've entered your credentials, you can go to your Gutenberg editor and start embedding our web components into your site.

### Supported page builders ###
* Gutenberg
* Avada
* Elementor
* WPBakery
* Divi support can be found here: [https://github.com/wink-travel/affiliate-wordpress-divi-plugin]


### Short codes ###

* [winklookup] - Works with ranked content grid. Type in a place you want to visit and have inventory displayed.
* [winksearch] - Simple button to open up itinerary form.
* [winkaccount] - Account: Button to let you authenticate. Once authenticated, it turns into a dropdown with account options.
* [winkitinerary] - Same as search button, only it contains itinerary information as button text.
* [winkcontent] - Lets you embed the inventory you've selected on Wink directly into a page or post.

### Options ###
There are 2 ways to use our components:

1. Use our page builder support to drag and drop features and inventory into your posts and pages.
2. Using our short codes... There is one for each of our Web Components.

### Support ###

For questions and support, please proceed in this order:

1. Read our documentation at [Wink Academy](https://academy.wink.travel).
2. If you think you found a bug, please create an issue here and describe the problem.
3. If you need immediate support, e-mail us at [developer@wink.travel](mailto:developer@wink.travel).
### Contributing and releasing ###

This repository is **trunk-based**: `master` is the only long-lived branch (there is no `develop` and no git flow).
Branch from `master`, open the pull request against `master`, and use [Conventional Commits](https://www.conventionalcommits.org/)
(`feat:`, `fix:`, ...). Every merge runs the PHPUnit tests in Bamboo; nothing is deployed from a merge.

A release is a bare-semver tag on `master`, for example `1.5.3`, created by `./release.bash` (run `--dry-run` first; it
needs `git-cliff`, `gh` and a clean, up-to-date `master`). It works out the next version from the commits, writes it into
`wink.php` (`Version`) and `README.txt` (`Version` and `Stable tag`), adds the `CHANGELOG.md` entry, commits, tags, pushes
the commit and the tag together, and creates the GitHub release. **Never push a bare-semver tag by hand.**

Pushing the tag makes Bamboo test the tagged commit and then publish it to the
[WordPress.org plugin SVN](https://plugins.svn.wordpress.org/wink2travel): `trunk`, `assets/` (from `.wordpress-org/`) and
`tags/<version>`. See [bamboo-specs/README.md](bamboo-specs/README.md) and `scripts/deployToWordPressOrg.bash`. A published
SVN tag is permanent; to fix a bad release, fix forward on `master` and release a new patch version.
