FROM nginx:1.27.5-alpine

# Remove the default Nginx configuration file
RUN rm /etc/nginx/conf.d/default.conf -f

COPY default.conf /etc/nginx/conf.d/default.conf

# Expose port 80 (Nginx will listen on this port inside the container)
EXPOSE 80

# Command to run Nginx in the foreground
CMD ["nginx", "-g", "daemon off;"]
