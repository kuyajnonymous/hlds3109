FROM nginx:1.17.8-alpine

# Copy the public directory
COPY ./nginx/index.php /app/public/index.php

# Copy the nginx config file
COPY ./nginx/nginx.conf /etc/nginx/conf.d/default.conf
