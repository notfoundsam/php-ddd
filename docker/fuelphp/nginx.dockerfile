ARG NGINX_IMAGE=nginx:alpine
ARG ASSETS_IMAGE=node:24-alpine

FROM $ASSETS_IMAGE AS assets
WORKDIR /app
#COPY ./package.json /app/package.json
#COPY ./yarn.lock /app/yarn.lock
#RUN yarn install
#COPY ./frontend/src /app/src
#RUN yarn build

FROM $NGINX_IMAGE

COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/nginx_default.conf /etc/nginx/conf.d/default.conf
#COPY --from=assets /app/public/assets /app/public/assets
