FROM registry.ecoex.ir/docker-hub-proxy/nginxinc/nginx-unprivileged:stable

COPY deployment/prod/docker/default.conf /etc/nginx/conf.d/

COPY public /var/www/public

# Expose both application and monitoring ports
EXPOSE 8080 2019


