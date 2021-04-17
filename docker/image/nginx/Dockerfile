#https://hub.docker.com/_/nginx
FROM nginx:stable-alpine AS build
LABEL autodelete="true"
#https://serverfault.com/a/683651
RUN apk add --no-cache tzdata

FROM build
COPY . .
