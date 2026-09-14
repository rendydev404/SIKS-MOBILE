FROM node:20-bullseye-slim

# Install Chromium and required fonts for Puppeteer
RUN apt-get update && apt-get install -y --no-install-recommends \
    chromium \
    fonts-liberation \
    fonts-freefont-ttf \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    NODE_ENV=production

WORKDIR /app

COPY package*.json ./
RUN npm ci --only=production

COPY src/ ./src/

# Directory for persistent sessions
RUN mkdir -p /data/sessions

VOLUME ["/data/sessions"]

EXPOSE 3050

CMD ["node", "src/server.js"]
