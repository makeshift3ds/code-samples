# Connect Portal
[![Code Climate](https://codeclimate.com/repos/56fdc0c3bb4dca00600097de/badges/b1a2b60ce67cb0d4a67d/gpa.svg)](https://codeclimate.com/repos/56fdc0c3bb4dca00600097de/feed)
[![Issue Count](https://codeclimate.com/repos/56fdc0c3bb4dca00600097de/badges/b1a2b60ce67cb0d4a67d/issue_count.svg)](https://codeclimate.com/repos/56fdc0c3bb4dca00600097de/feed)

## Prerequisites

You will need the following things properly installed on your computer.

* [Git](http://git-scm.com/)
* [Node.js](http://nodejs.org/) (with NPM) and [Bower](http://bower.io/)
* [EmberCLI](http://www.ember-cli.com/)

## Installation

* `git clone <repository-url>` this repository
* change into the new directory
* `npm install`
* `npm install -g phantomjs`
* `bower install`
* `brew install watchman`

## Running / Development

* `ember server`
* start local connect_api application `foreman start`
* Visit your app at `http://localhost:4200`

## Testing

* start atom-api in test mode `RAILS_ENV=test rails s`
* run test suite in browser: `http://localhost:4200/tests`
* run tests in cli: `ember test`

## Deployment

Deployment uses the [ember-deploy
addon](https://github.com/LevelBossMike/ember-deploy) for ["Lightning
Fast" deploys](https://www.youtube.com/watch?v=QZVYP3cPcWQ). The
build and deploy process has several steps:
* create a 'deploy.json' in the root directory with AWS credentials and
  the target S3 bucket listed. See
[deploy.json.example](https://github.com/Hello-Labs/connect_portal/blob/master/deploy.json.example) for a
template.
* build the distribution with `ember build --environment production`. The
  result appears in './dist'.
* uploading the contents of the build to the S3 bucket defined in
  'deploy.json' using `ember deploy:assets --environment production`
* uploading the index.html for the current build to the versioning
  system with `ember deploy:index --environment production`
* seeing the available versions that can be set as the deployed version
  `ember deploy:list --environment production`. The output will look
like:
```
Last 10 uploaded revisions:

|    employee-portal-cli:2a79136
|    employee-portal-cli:6328a33
|    employee-portal-cli:6450e6b
|    employee-portal-cli:8d5728c
|    employee-portal-cli:abcd123
|    employee-portal-cli:cde7615
| => employee-portal-cli:f00f565

# => - current revision
```
* setting the current deployed version `ember deploy:activate --revision
  employee-portal-cli:abcd123 --environment production`
* you should now see that `employee-portal-cli:abcd123` is the
  currently selected version with `ember deploy:list --environment
production`. Now you should see:
```
Last 10 uploaded revisions:

|    employee-portal-cli:2a79136
|    employee-portal-cli:6328a33
|    employee-portal-cli:6450e6b
|    employee-portal-cli:8d5728c
| => employee-portal-cli:abcd123
|    employee-portal-cli:cde7615
|    employee-portal-cli:f00f565

# => - current revision
```

Several things to NOTE:
* This allows us to change, in a few seconds, which version of the app
  is deployed.
* `--environment production` that is appended in each of the commands
  corresponds to the top-level key named in 'deploy.json'
* There is a different [ember-cli-deploy
  project](https://www.npmjs.com/package/ember-cli-deploy) that does not allow us
  to use our own store for deployed versions, i.e. doesn't support
anything other than Redis, so we are using
[ember-deploy](https://www.npmjs.com/package/ember-deploy) instead. We are
using S3 for our version store instead of Redis.
* see [this video](https://www.youtube.com/watch?v=Ro2_I5vtTIg) for a
  great explanation by the maintainer, Michael Klein on how lightning
fast deployments work.

## Further Reading / Useful Links

* ember: http://emberjs.com/
* ember-cli: http://www.ember-cli.com/
* Development Browser Extensions
  * [ember inspector for chrome](https://chrome.google.com/webstore/detail/ember-inspector/bmdblncegkenkacieihfhpjfppoconhi)
  * [ember inspector for firefox](https://addons.mozilla.org/en-US/firefox/addon/ember-inspector/)