#!/bin/sh

# Wait for Ollama to be ready
until curl -s http://ollama:11434/api/tags > /dev/null; do
  echo "Waiting for Ollama to be ready..."
  sleep 2
done

echo "Ollama is ready!"

# Start the main service
exec "$@"
