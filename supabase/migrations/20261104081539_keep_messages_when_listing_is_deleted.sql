ALTER TABLE public.messages
    DROP CONSTRAINT messages_product_id_fkey,
    ADD CONSTRAINT messages_product_id_fkey
        FOREIGN KEY (product_id)
        REFERENCES public.products(id)
        ON DELETE SET NULL;
