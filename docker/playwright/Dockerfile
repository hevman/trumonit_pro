FROM mcr.microsoft.com/playwright:v1.58.2-jammy

WORKDIR /app

# Avoid downloading browsers during npm install (browsers already exist in the base image).
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1

COPY package.json ./
RUN npm install --omit=dev

COPY server.js ./

ENV PORT=3000
EXPOSE 3000

CMD ["node", "server.js"]
