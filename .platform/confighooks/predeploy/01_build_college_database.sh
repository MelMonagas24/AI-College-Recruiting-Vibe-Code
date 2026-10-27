#!/bin/bash
#
# Rebuilds the college database after configuration updates.
#
# Changing an environment property (such as adding ANTHROPIC_API_KEY) makes
# Elastic Beanstalk set up the app again from the uploaded ZIP, which does not
# contain the database file. Configuration updates run .platform/confighooks
# instead of .platform/hooks, so this runs the same build script used for
# application deployments. If it fails, the update stops and the previous
# version keeps running.

set -euo pipefail

exec bash .platform/hooks/predeploy/01_build_college_database.sh
