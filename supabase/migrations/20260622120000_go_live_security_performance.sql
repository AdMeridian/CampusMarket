-- Go-live hardening: Supabase Advisor security + performance fixes.

-- ---------------------------------------------------------------------------
-- 1. Server-only tables: enable RLS (admin audit + rate limits)
-- ---------------------------------------------------------------------------
ALTER TABLE public.admin_audit_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.rate_limit_buckets ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS admin_audit_log_no_client_access ON public.admin_audit_log;
CREATE POLICY admin_audit_log_no_client_access
ON public.admin_audit_log
FOR ALL
TO authenticated, anon
USING (false)
WITH CHECK (false);

DROP POLICY IF EXISTS rate_limit_buckets_no_client_access ON public.rate_limit_buckets;
CREATE POLICY rate_limit_buckets_no_client_access
ON public.rate_limit_buckets
FOR ALL
TO authenticated, anon
USING (false)
WITH CHECK (false);

-- ---------------------------------------------------------------------------
-- 2. message_translations: remove permissive policy; participant read only
-- ---------------------------------------------------------------------------
ALTER TABLE public.message_translations ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow public access to message_translations" ON public.message_translations;

CREATE POLICY message_translations_participant_select
ON public.message_translations
FOR SELECT
TO authenticated
USING (
  EXISTS (
    SELECT 1
    FROM public.messages m
    WHERE m.id = message_translations.message_id
      AND (
        m.sender_id = (SELECT public.current_app_user_id())
        OR m.receiver_id = (SELECT public.current_app_user_id())
      )
  )
);

-- ---------------------------------------------------------------------------
-- 3. Storage: public bucket URLs work without a broad list-all SELECT policy
-- ---------------------------------------------------------------------------
DROP POLICY IF EXISTS "Allow Public Select" ON storage.objects;

-- ---------------------------------------------------------------------------
-- 4. chatbot rate-limit RPC: service_role only
-- ---------------------------------------------------------------------------
REVOKE ALL ON FUNCTION public.chatbot_rate_limit_allow(text, integer, integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.chatbot_rate_limit_allow(text, integer, integer) FROM anon;
REVOKE ALL ON FUNCTION public.chatbot_rate_limit_allow(text, integer, integer) FROM authenticated;
GRANT EXECUTE ON FUNCTION public.chatbot_rate_limit_allow(text, integer, integer) TO service_role;

-- ---------------------------------------------------------------------------
-- 5. Foreign key covering indexes (performance advisor)
-- ---------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_deal_confirmations_dismissed_by ON public.deal_confirmations (dismissed_by);
CREATE INDEX IF NOT EXISTS idx_message_email_alerts_product_id ON public.message_email_alerts (product_id);
CREATE INDEX IF NOT EXISTS idx_message_email_alerts_sender_id ON public.message_email_alerts (sender_id);
CREATE INDEX IF NOT EXISTS idx_messages_product_id ON public.messages (product_id);
CREATE INDEX IF NOT EXISTS idx_messages_sender_id ON public.messages (sender_id);
CREATE INDEX IF NOT EXISTS idx_orders_buyer_id ON public.orders (buyer_id);
CREATE INDEX IF NOT EXISTS idx_orders_product_id ON public.orders (product_id);
CREATE INDEX IF NOT EXISTS idx_product_images_product_id ON public.product_images (product_id);
CREATE INDEX IF NOT EXISTS idx_product_tags_tag_id ON public.product_tags (tag_id);
CREATE INDEX IF NOT EXISTS idx_product_views_user_id ON public.product_views (user_id);
CREATE INDEX IF NOT EXISTS idx_products_category_id ON public.products (category_id);
CREATE INDEX IF NOT EXISTS idx_products_user_id ON public.products (user_id);
CREATE INDEX IF NOT EXISTS idx_promotion_payments_approved_by ON public.promotion_payments (approved_by);
CREATE INDEX IF NOT EXISTS idx_ratings_product_id ON public.ratings (product_id);
CREATE INDEX IF NOT EXISTS idx_ratings_seller_id ON public.ratings (seller_id);
CREATE INDEX IF NOT EXISTS idx_reports_resolved_by_admin_id ON public.reports (resolved_by_admin_id);
CREATE INDEX IF NOT EXISTS idx_wishlists_product_id ON public.wishlists (product_id);

-- ---------------------------------------------------------------------------
-- 6. Split ALL write policies to avoid duplicate permissive SELECT policies
-- ---------------------------------------------------------------------------
DROP POLICY IF EXISTS product_images_write_owner_product ON public.product_images;
CREATE POLICY product_images_insert_owner_product ON public.product_images
FOR INSERT TO authenticated
WITH CHECK (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_images.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);
CREATE POLICY product_images_update_owner_product ON public.product_images
FOR UPDATE TO authenticated
USING (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_images.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
)
WITH CHECK (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_images.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);
CREATE POLICY product_images_delete_owner_product ON public.product_images
FOR DELETE TO authenticated
USING (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_images.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);

DROP POLICY IF EXISTS product_tags_write_owner_product ON public.product_tags;
CREATE POLICY product_tags_insert_owner_product ON public.product_tags
FOR INSERT TO authenticated
WITH CHECK (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_tags.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);
CREATE POLICY product_tags_update_owner_product ON public.product_tags
FOR UPDATE TO authenticated
USING (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_tags.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
)
WITH CHECK (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_tags.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);
CREATE POLICY product_tags_delete_owner_product ON public.product_tags
FOR DELETE TO authenticated
USING (
  EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = product_tags.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);

DROP POLICY IF EXISTS transactions_write_related_parties ON public.transactions;
CREATE POLICY transactions_insert_related_parties ON public.transactions
FOR INSERT TO authenticated
WITH CHECK (
  EXISTS (
    SELECT 1
    FROM public.orders o
    JOIN public.products p ON p.id = o.product_id
    WHERE o.id = transactions.order_id
      AND (
        o.buyer_id = (SELECT public.current_app_user_id())
        OR p.user_id = (SELECT public.current_app_user_id())
      )
  )
);
CREATE POLICY transactions_update_related_parties ON public.transactions
FOR UPDATE TO authenticated
USING (
  EXISTS (
    SELECT 1
    FROM public.orders o
    JOIN public.products p ON p.id = o.product_id
    WHERE o.id = transactions.order_id
      AND (
        o.buyer_id = (SELECT public.current_app_user_id())
        OR p.user_id = (SELECT public.current_app_user_id())
      )
  )
)
WITH CHECK (
  EXISTS (
    SELECT 1
    FROM public.orders o
    JOIN public.products p ON p.id = o.product_id
    WHERE o.id = transactions.order_id
      AND (
        o.buyer_id = (SELECT public.current_app_user_id())
        OR p.user_id = (SELECT public.current_app_user_id())
      )
  )
);

-- ---------------------------------------------------------------------------
-- 7. RLS initplan: use (select ...) / current_app_user_id() on flagged policies
-- ---------------------------------------------------------------------------
DROP POLICY IF EXISTS users_select_own ON public.users;
CREATE POLICY users_select_own ON public.users
FOR SELECT TO authenticated
USING (id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS users_update_own ON public.users;
CREATE POLICY users_update_own ON public.users
FOR UPDATE TO authenticated
USING (id = (SELECT public.current_app_user_id()))
WITH CHECK (id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS products_insert_own ON public.products;
CREATE POLICY products_insert_own ON public.products
FOR INSERT TO authenticated
WITH CHECK (user_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS products_update_own ON public.products;
CREATE POLICY products_update_own ON public.products
FOR UPDATE TO authenticated
USING (user_id = (SELECT public.current_app_user_id()))
WITH CHECK (user_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS products_delete_own ON public.products;
CREATE POLICY products_delete_own ON public.products
FOR DELETE TO authenticated
USING (user_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS wishlists_all_own ON public.wishlists;
CREATE POLICY wishlists_all_own ON public.wishlists
FOR ALL TO authenticated
USING (user_id = (SELECT public.current_app_user_id()))
WITH CHECK (user_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS orders_select_buyer_or_seller ON public.orders;
CREATE POLICY orders_select_buyer_or_seller ON public.orders
FOR SELECT TO authenticated
USING (
  buyer_id = (SELECT public.current_app_user_id())
  OR EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = orders.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);

DROP POLICY IF EXISTS orders_insert_buyer ON public.orders;
CREATE POLICY orders_insert_buyer ON public.orders
FOR INSERT TO authenticated
WITH CHECK (buyer_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS orders_update_buyer_or_seller ON public.orders;
CREATE POLICY orders_update_buyer_or_seller ON public.orders
FOR UPDATE TO authenticated
USING (
  buyer_id = (SELECT public.current_app_user_id())
  OR EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = orders.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
)
WITH CHECK (
  buyer_id = (SELECT public.current_app_user_id())
  OR EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = orders.product_id
      AND p.user_id = (SELECT public.current_app_user_id())
  )
);

DROP POLICY IF EXISTS transactions_select_related_parties ON public.transactions;
CREATE POLICY transactions_select_related_parties ON public.transactions
FOR SELECT TO authenticated
USING (
  EXISTS (
    SELECT 1
    FROM public.orders o
    JOIN public.products p ON p.id = o.product_id
    WHERE o.id = transactions.order_id
      AND (
        o.buyer_id = (SELECT public.current_app_user_id())
        OR p.user_id = (SELECT public.current_app_user_id())
      )
  )
);

DROP POLICY IF EXISTS ratings_select_related ON public.ratings;
CREATE POLICY ratings_select_related ON public.ratings
FOR SELECT TO authenticated
USING (
  reviewer_id = (SELECT public.current_app_user_id())
  OR seller_id = (SELECT public.current_app_user_id())
);

DROP POLICY IF EXISTS ratings_insert_reviewer ON public.ratings;
CREATE POLICY ratings_insert_reviewer ON public.ratings
FOR INSERT TO authenticated
WITH CHECK (reviewer_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS ratings_update_reviewer ON public.ratings;
CREATE POLICY ratings_update_reviewer ON public.ratings
FOR UPDATE TO authenticated
USING (reviewer_id = (SELECT public.current_app_user_id()))
WITH CHECK (reviewer_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS reports_all_own ON public.reports;
CREATE POLICY reports_all_own ON public.reports
FOR ALL TO authenticated
USING (reporter_id = (SELECT public.current_app_user_id()))
WITH CHECK (reporter_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS email_verifications_all_own ON public.email_verifications;
CREATE POLICY email_verifications_all_own ON public.email_verifications
FOR ALL TO authenticated
USING (user_id = (SELECT public.current_app_user_id()))
WITH CHECK (user_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS deal_confirmations_select_participants ON public.deal_confirmations;
CREATE POLICY deal_confirmations_select_participants ON public.deal_confirmations
FOR SELECT TO authenticated
USING (
  buyer_id = (SELECT public.current_app_user_id())
  OR seller_id = (SELECT public.current_app_user_id())
);

DROP POLICY IF EXISTS deal_confirmations_insert_participants ON public.deal_confirmations;
CREATE POLICY deal_confirmations_insert_participants ON public.deal_confirmations
FOR INSERT TO authenticated
WITH CHECK (
  (
    buyer_id = (SELECT public.current_app_user_id())
    OR seller_id = (SELECT public.current_app_user_id())
  )
  AND EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = deal_confirmations.product_id
      AND p.user_id = deal_confirmations.seller_id
  )
);

DROP POLICY IF EXISTS deal_confirmations_update_participants ON public.deal_confirmations;
CREATE POLICY deal_confirmations_update_participants ON public.deal_confirmations
FOR UPDATE TO authenticated
USING (
  buyer_id = (SELECT public.current_app_user_id())
  OR seller_id = (SELECT public.current_app_user_id())
)
WITH CHECK (
  (
    buyer_id = (SELECT public.current_app_user_id())
    OR seller_id = (SELECT public.current_app_user_id())
  )
  AND EXISTS (
    SELECT 1 FROM public.products p
    WHERE p.id = deal_confirmations.product_id
      AND p.user_id = deal_confirmations.seller_id
  )
);

DROP POLICY IF EXISTS "Authenticated users can select promotion payments" ON public.promotion_payments;
CREATE POLICY promotion_payments_select_authenticated ON public.promotion_payments
FOR SELECT TO authenticated
USING ((SELECT auth.role()) = 'authenticated');

DROP POLICY IF EXISTS "Allow public insert access" ON public.product_views;
CREATE POLICY product_views_insert_public ON public.product_views
FOR INSERT TO anon, authenticated
WITH CHECK ((SELECT auth.role()) IN ('anon', 'authenticated'));

-- messages + notifications already use current_app_user_id from prior migration;
-- re-apply with subselect wrapper for initplan lint.
DROP POLICY IF EXISTS messages_participants_select ON public.messages;
CREATE POLICY messages_participants_select ON public.messages
FOR SELECT TO authenticated
USING (
  sender_id = (SELECT public.current_app_user_id())
  OR receiver_id = (SELECT public.current_app_user_id())
);

DROP POLICY IF EXISTS messages_participants_insert ON public.messages;
CREATE POLICY messages_participants_insert ON public.messages
FOR INSERT TO authenticated
WITH CHECK (sender_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS messages_participants_update ON public.messages;
CREATE POLICY messages_participants_update ON public.messages
FOR UPDATE TO authenticated
USING (
  sender_id = (SELECT public.current_app_user_id())
  OR receiver_id = (SELECT public.current_app_user_id())
)
WITH CHECK (
  sender_id = (SELECT public.current_app_user_id())
  OR receiver_id = (SELECT public.current_app_user_id())
);

DROP POLICY IF EXISTS notifications_select_own ON public.notifications;
CREATE POLICY notifications_select_own ON public.notifications
FOR SELECT TO authenticated
USING (user_id = (SELECT public.current_app_user_id()));

DROP POLICY IF EXISTS notifications_update_own ON public.notifications;
CREATE POLICY notifications_update_own ON public.notifications
FOR UPDATE TO authenticated
USING (user_id = (SELECT public.current_app_user_id()))
WITH CHECK (user_id = (SELECT public.current_app_user_id()));
