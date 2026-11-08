FROM node:24-alpine

# Install Haraka and required plugins
RUN npm install -g Haraka@3.0.3 \
    && npm install -g haraka-plugin-limit

# Create haraka user and directories
RUN adduser -D -h /opt/haraka haraka \
    && mkdir -p /opt/haraka/config \
    && mkdir -p /opt/haraka/queue \
    && mkdir -p /opt/haraka/logs

# Initialize Haraka. haraka -i writes the build machine's name to config/me, which
# would be the name of every installation: the entrypoint writes it from
# NMI_HOSTNAME or APP_URL instead (the name of each message comes from NetSendo)
WORKDIR /opt/haraka
RUN haraka -i /opt/haraka \
    && rm -f /opt/haraka/config/me

# Copy configuration files and NetSendo's plugins
COPY config/ /opt/haraka/config/
COPY plugins/ /opt/haraka/plugins/

# Logins written by NetSendo (nmi-auth volume, mounted read-only)
RUN mkdir -p /opt/haraka/auth

# Start-up: config/me and the listen addresses (NMI_MTA_LISTEN); the healthcheck
# connects to the first listen address
COPY docker-entrypoint.sh /usr/local/bin/netsendo-mta-entrypoint
COPY healthcheck.sh /usr/local/bin/netsendo-mta-healthcheck
RUN chmod 755 /usr/local/bin/netsendo-mta-entrypoint /usr/local/bin/netsendo-mta-healthcheck

# Set permissions
RUN chown -R haraka:haraka /opt/haraka

# SMTP ports: 2525 where NetSendo hands mail over, 25 and 587 for older setups
EXPOSE 25 587 2525

# Switch to haraka user
USER haraka

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD netsendo-mta-healthcheck

# Start Haraka
ENTRYPOINT ["/usr/local/bin/netsendo-mta-entrypoint"]
CMD ["haraka", "-c", "/opt/haraka"]
