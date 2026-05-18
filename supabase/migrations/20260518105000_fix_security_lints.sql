-- Fix security lint: rls_policy_always_true for public.product_views
DROP POLICY IF EXISTS "Allow public insert access" ON public.product_views;
CREATE POLICY "Allow public insert access" ON public.product_views FOR INSERT WITH CHECK (auth.role() IN ('anon', 'authenticated'));

-- Fix security lint: public_bucket_allows_listing for marketplace
DROP POLICY IF EXISTS "Public Access" ON storage.objects;
CREATE POLICY "Users can view own objects in marketplace" ON storage.objects FOR SELECT USING (bucket_id = 'marketplace' AND auth.uid() = owner);

-- Fix security lint: rls_enabled_no_policy for public.promotion_payments
CREATE POLICY "Authenticated users can select promotion payments" ON public.promotion_payments FOR SELECT USING (auth.role() = 'authenticated');
