FROM node:24-alpine

# Install build dependencies that sqlite3 might need if it compiles from source
RUN apk add --no-cache python3 make g++ sqlite-dev

WORKDIR /usr/src/app

COPY socket/package.json ./
COPY socket/package-lock.json ./

# Install dependencies.
# sqlite3's install script will attempt to download a pre-built binary.
# If that fails, it will attempt to compile from source using the tools installed above.
# The --verbose flag can give insight into what sqlite3 is doing.
RUN npm install --production --verbose

COPY socket/ ./

EXPOSE ${SOCKET_PORT}

CMD [ "node", "server.js" ]
