# =============================================================================
# Builder stage - build PHP extensions, vendor, and generate Scribe docs
# =============================================================================
FROM registry.ecoex.ir/docker-base-images/wallgold-backend-base-image:frankenphp-1-php8.5 AS builder

ENV DEBIAN_FRONTEND=noninteractive

# Build dependencies (ONLY for build time)

# Composer

WORKDIR /var/www

# Install production dependencies only
COPY composer.json composer.lock ./

RUN composer config --global repo.packagist composer https://artifact.ecoex.ir/repository/wallgold-composer-proxy/

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --classmap-authoritative \
    --no-scripts \
    && rm -rf /root/.composer/cache

COPY . .

# Regenerate composer autoloader after copying application code
# This ensures all classes are available before package:discover loads config files
RUN composer dump-autoload --no-interaction --optimize --classmap-authoritative

# Ensure cache directories exist and have proper permissions before Scribe generation
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views database \
    && touch database/database.sqlite \
    && chmod -R 775 bootstrap/cache storage/framework database

# Run Laravel package discovery now that artisan is available
# Autoloader is already regenerated, so config files can safely use class constants
RUN php artisan package:discover --ansi

# =============================================================================
# Final production image - minimal runtime
# =============================================================================
FROM builder AS franken_base

ENV DEBIAN_FRONTEND=noninteractive

# Runtime dependencies ONLY

RUN ldconfig

# Application files (including Scribe docs output)
WORKDIR /var/www

# Copy startup script
COPY --from=builder /var/www/deployment/scripts/start-frankenphp.sh /usr/local/bin/start-frankenphp.sh
RUN chmod +x /usr/local/bin/start-frankenphp.sh

RUN php artisan octane:install --server=frankenphp --no-interaction

# Generate Scribe documentation (output must remain)
RUN php artisan scribe:generate --config=scribe --verbose || echo -e "\033[31mWARNING: Scribe documentation generation failed\033[0m" && \
    php artisan scribe:generate --config=scribe-admin --verbose || echo -e "\033[31mWARNING: Scribe admin documentation generation failed\033[0m" && \
    php artisan scribe:generate --config=scribe-api --verbose || echo -e "\033[31mWARNING: Scribe api documentation generation failed\033[0m"


# Create non-root user
RUN groupadd -g 1000 franken && \
    useradd -u 1000 -g 1000 -m franken && \
    mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views && \
    chmod -R 775 bootstrap/cache storage/framework && \
    chown -R 1000:1000 /var/www

# Fix FrankenPHP binary permissions for non-root user
RUN chmod +x /usr/local/bin/frankenphp && \
    chown 1000:1000 /usr/local/bin/frankenphp

# Install Octane (FrankenPHP)
ARG SENTRY_VERSION
ENV SENTRY_VERSION=${SENTRY_VERSION}

# Switch to non-root user
USER 1000:1000

# Expose FrankenPHP HTTP port and metrics port
EXPOSE 8081 2019

# Default to run Laravel Octane with FrankenPHP
ENV SERVER_NAME=:8081
ENV DOCUMENT_ROOT=/var/www/public
ENV FRANKENPHP_CONFIG=""


# Use startup script to run  Octane
CMD ["/usr/local/bin/start-frankenphp.sh"]

# =============================================================================
# Test stage (CI only)
# - dev dependencies
# - test execution
# =============================================================================
FROM builder AS test

# Install all dependencies (production + test) - cached layer
# This will reuse the vendor directory from builder stage and add dev dependencies
# composer.json and composer.lock are already copied in builder stage
RUN composer install \
    --dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader && \
    rm -rf /root/.composer/cache

# Ensure cache directories exist and have proper permissions
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views \
    && chmod -R 775 bootstrap/cache storage/framework

ARG SENTRY_VERSION
ENV SENTRY_VERSION=${SENTRY_VERSION}
