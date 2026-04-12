-- Phase A: pgvector schema for demo RAG
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS demo_vector_chunks (
  session_id TEXT NOT NULL,
  id TEXT NOT NULL,
  doc_id TEXT NOT NULL,
  title TEXT NOT NULL,
  text TEXT NOT NULL,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  embedding VECTOR(96) NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (session_id, id)
);

CREATE INDEX IF NOT EXISTS idx_demo_vector_session ON demo_vector_chunks(session_id);
CREATE INDEX IF NOT EXISTS idx_demo_vector_doc ON demo_vector_chunks(session_id, doc_id);
CREATE INDEX IF NOT EXISTS idx_demo_vector_embedding_hnsw ON demo_vector_chunks USING hnsw (embedding vector_cosine_ops);

-- env vars expected by app (or set DEMO_VECTOR_PG_DSN directly):
-- DEMO_VECTOR_PG_HOST=127.0.0.1
-- DEMO_VECTOR_PG_PORT=5432
-- DEMO_VECTOR_PG_DB=demo_rag
-- DEMO_VECTOR_PG_USER=demo_user
-- DEMO_VECTOR_PG_PASS=change_me
