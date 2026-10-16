FROM node:24-slim

# Optional extra root CAs (e.g. a corporate proxy or antivirus doing TLS inspection).
# Drop *.crt files into docker/certs/ - the directory is empty by default.
COPY docker/certs/ /usr/local/share/ca-certificates/
RUN cat /usr/local/share/ca-certificates/*.crt > /usr/local/share/extra-ca.pem 2>/dev/null; true
ENV NODE_EXTRA_CA_CERTS=/usr/local/share/extra-ca.pem

WORKDIR /app
EXPOSE 5173

CMD ["sh", "-c", "[ -d node_modules ] || npm ci; npm run dev -- --host 0.0.0.0"]
