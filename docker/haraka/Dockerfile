FROM node:20-alpine

# Install Haraka and required plugins
RUN npm install -g Haraka@3.0.3 \
    && npm install -g haraka-plugin-auth-flat-file \
    && npm install -g haraka-plugin-limit

# Create haraka user and directories
RUN adduser -D -h /opt/haraka haraka \
    && mkdir -p /opt/haraka/config \
    && mkdir -p /opt/haraka/queue \
    && mkdir -p /opt/haraka/logs

# Initialize Haraka
WORKDIR /opt/haraka
RUN haraka -i /opt/haraka

# Copy configuration files
COPY config/ /opt/haraka/config/

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
CMD ["haraka", "-c", "/opt/haraka"]
