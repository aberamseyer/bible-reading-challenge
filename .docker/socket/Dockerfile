FROM node:24-alpine AS builder

RUN apk add --no-cache python3 make g++ sqlite-dev

WORKDIR /usr/src/app

COPY socket/package.json socket/package-lock.json ./

RUN npm ci --production --verbose

# Stage 2: Final Image
FROM node:24-alpine

# Set NODE_ENV to production (good practice, might reduce some package sizes)
ENV NODE_ENV production
# Set SOCKET_PORT, can be overridden by .env file in docker-compose
ENV SOCKET_PORT 8085

WORKDIR /usr/src/app

# Install only runtime dependencies for sqlite3 if it doesn't bundle them.
# node:alpine images already include basic libs. sqlite3 npm package usually handles this well.
# For sqlite3, the native module is in node_modules, so usually no extra apk add needed in final stage
# unless specific shared libraries are missing, which is rare for sqlite.
# RUN apk add --no-cache sqlite-libs # Usually not needed if sqlite3 npm package is well-built

# Copy package.json and package-lock.json (optional, but good for metadata)
COPY socket/package.json socket/package-lock.json ./


COPY --from=builder /usr/src/app/node_modules ./node_modules

COPY socket/ ./

EXPOSE ${SOCKET_PORT}

CMD [ "node", "server.js" ]
