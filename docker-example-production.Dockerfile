# Dockerfile for Ember CLI, PhantomJS, and Bower
FROM node:8.1.4

LABEL maintainer="kelliott@idplans.com" \
      version="1.0.0" \
      description="Ubuntu image running node for v5api. mount the v5api directory as a volume."

# app is running on port 80
EXPOSE 80

# set the default directory
WORKDIR /app

# copy application
COPY . /app

# add aws creds to the environment
ARG AWS_ACCESS_KEY_ID
ENV AWS_ACCESS_KEY_ID ${AWS_ACCESS_KEY_ID}

ARG AWS_SECRET_ACCESS_KEY
ENV AWS_SECRET_ACCESS_KEY ${AWS_SECRET_ACCESS_KEY}

ARG S3_BUCKET_NAME
ENV S3_BUCKET_NAME ${S3_BUCKET_NAME}

# use oauth token to get private github repos in package.json
ARG GITHUB_OAUTH
RUN node scripts/add-authentication ${GITHUB_OAUTH}

# install npm modules
RUN npm i --verbose --no-shrinkwrap

# update config with secrets
RUN npm run generate-config:production
