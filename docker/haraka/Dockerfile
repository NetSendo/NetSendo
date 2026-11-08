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
# would be the HELO name of every installation: the entrypoint writes it from
# NMI_HOSTNAME instead (without it Haraka uses the container's hostname)
WORKDIR /opt/haraka
RUN haraka -i /opt/haraka \
    && rm -f /opt/haraka/config/me

# Copy configuration files and NetSendo's plugins
COPY config/ /opt/haraka/config/
COPY plugins/ /opt/haraka/plugins/

# Logins written by NetSendo (nmi-auth volume, mounted read-only)
RUN mkdir -p /opt/haraka/auth

# Start-up: the HELO name from NMI_HOSTNAME
COPY docker-entrypoint.sh /usr/local/bin/netsendo-mta-entrypoint
RUN chmod 755 /usr/local/bin/netsendo-mta-entrypoint

# Set permissions
RUN chown -R haraka:haraka /opt/haraka

# Expose SMTP ports
# 25 - Standard SMTP (MTA to MTA)
# 587 - Submission (client to server)
# 465 - SMTPS (deprecated but still used)
EXPOSE 25 587 465

# Switch to haraka user
USER haraka

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD nc -z localhost 25 || exit 1

# Start Haraka
ENTRYPOINT ["/usr/local/bin/netsendo-mta-entrypoint"]
CMD ["haraka", "-c", "/opt/haraka"]
