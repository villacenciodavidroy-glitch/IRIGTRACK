--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5
-- Dumped by pg_dump version 17.5

-- Started on 2025-07-19 21:39:48

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 246 (class 1259 OID 17213)
-- Name: borrow_transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.borrow_transactions (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    quantity integer NOT NULL,
    borrowed_by character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'borrowed'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.borrow_transactions OWNER TO postgres;

--
-- TOC entry 245 (class 1259 OID 17212)
-- Name: borrow_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.borrow_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.borrow_transactions_id_seq OWNER TO postgres;

--
-- TOC entry 5026 (class 0 OID 0)
-- Dependencies: 245
-- Name: borrow_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.borrow_transactions_id_seq OWNED BY public.borrow_transactions.id;


--
-- TOC entry 223 (class 1259 OID 17059)
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 17066)
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- TOC entry 243 (class 1259 OID 17188)
-- Name: categories; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    category character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.categories OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 17187)
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categories_id_seq OWNER TO postgres;

--
-- TOC entry 5027 (class 0 OID 0)
-- Dependencies: 242
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- TOC entry 237 (class 1259 OID 17129)
-- Name: condition_numbers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.condition_numbers (
    id bigint NOT NULL,
    condition_number character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.condition_numbers OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 17128)
-- Name: condition_numbers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.condition_numbers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.condition_numbers_id_seq OWNER TO postgres;

--
-- TOC entry 5028 (class 0 OID 0)
-- Dependencies: 236
-- Name: condition_numbers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.condition_numbers_id_seq OWNED BY public.condition_numbers.id;


--
-- TOC entry 235 (class 1259 OID 17122)
-- Name: conditions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.conditions (
    id bigint NOT NULL,
    condition character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.conditions OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 17121)
-- Name: conditions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.conditions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.conditions_id_seq OWNER TO postgres;

--
-- TOC entry 5029 (class 0 OID 0)
-- Dependencies: 234
-- Name: conditions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.conditions_id_seq OWNED BY public.conditions.id;


--
-- TOC entry 229 (class 1259 OID 17091)
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 17090)
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- TOC entry 5030 (class 0 OID 0)
-- Dependencies: 228
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- TOC entry 239 (class 1259 OID 17136)
-- Name: items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.items (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    unit character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    pac character varying(255) NOT NULL,
    unit_value numeric(10,2) NOT NULL,
    date_acquired date NOT NULL,
    po_number character varying(255) NOT NULL,
    category_id bigint NOT NULL,
    location_id bigint NOT NULL,
    condition_id bigint,
    condition_number_id bigint,
    user_id bigint NOT NULL,
    image_path character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    deletion_reason text,
    quantity integer
);


ALTER TABLE public.items OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 17200)
-- Name: items2; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.items2 (
    id uuid NOT NULL,
    unit character varying(50),
    description character varying(255)
);


ALTER TABLE public.items2 OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 17135)
-- Name: items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.items_id_seq OWNER TO postgres;

--
-- TOC entry 5031 (class 0 OID 0)
-- Dependencies: 238
-- Name: items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.items_id_seq OWNED BY public.items.id;


--
-- TOC entry 227 (class 1259 OID 17083)
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 17074)
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 17073)
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- TOC entry 5032 (class 0 OID 0)
-- Dependencies: 225
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- TOC entry 233 (class 1259 OID 17115)
-- Name: locations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.locations (
    id bigint NOT NULL,
    location character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.locations OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 17114)
-- Name: locations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.locations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.locations_id_seq OWNER TO postgres;

--
-- TOC entry 5033 (class 0 OID 0)
-- Dependencies: 232
-- Name: locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.locations_id_seq OWNED BY public.locations.id;


--
-- TOC entry 218 (class 1259 OID 17026)
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- TOC entry 217 (class 1259 OID 17025)
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- TOC entry 5034 (class 0 OID 0)
-- Dependencies: 217
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- TOC entry 221 (class 1259 OID 17043)
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- TOC entry 231 (class 1259 OID 17103)
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 17102)
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO postgres;

--
-- TOC entry 5035 (class 0 OID 0)
-- Dependencies: 230
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- TOC entry 241 (class 1259 OID 17167)
-- Name: q_r_codes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.q_r_codes (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    qr_code_data text NOT NULL,
    image_path character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.q_r_codes OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 17166)
-- Name: q_r_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.q_r_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.q_r_codes_id_seq OWNER TO postgres;

--
-- TOC entry 5036 (class 0 OID 0)
-- Dependencies: 240
-- Name: q_r_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.q_r_codes_id_seq OWNED BY public.q_r_codes.id;


--
-- TOC entry 222 (class 1259 OID 17050)
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 17033)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    fullname character varying(255) NOT NULL,
    username character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    role character varying(255) NOT NULL,
    image character varying(255),
    location_id bigint NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 17032)
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- TOC entry 5037 (class 0 OID 0)
-- Dependencies: 219
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- TOC entry 4788 (class 2604 OID 17216)
-- Name: borrow_transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.borrow_transactions ALTER COLUMN id SET DEFAULT nextval('public.borrow_transactions_id_seq'::regclass);


--
-- TOC entry 4787 (class 2604 OID 17191)
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- TOC entry 4782 (class 2604 OID 17132)
-- Name: condition_numbers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.condition_numbers ALTER COLUMN id SET DEFAULT nextval('public.condition_numbers_id_seq'::regclass);


--
-- TOC entry 4781 (class 2604 OID 17125)
-- Name: conditions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conditions ALTER COLUMN id SET DEFAULT nextval('public.conditions_id_seq'::regclass);


--
-- TOC entry 4777 (class 2604 OID 17094)
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- TOC entry 4783 (class 2604 OID 17139)
-- Name: items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items ALTER COLUMN id SET DEFAULT nextval('public.items_id_seq'::regclass);


--
-- TOC entry 4776 (class 2604 OID 17077)
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- TOC entry 4780 (class 2604 OID 17118)
-- Name: locations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations ALTER COLUMN id SET DEFAULT nextval('public.locations_id_seq'::regclass);


--
-- TOC entry 4774 (class 2604 OID 17029)
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- TOC entry 4779 (class 2604 OID 17106)
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- TOC entry 4784 (class 2604 OID 17170)
-- Name: q_r_codes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.q_r_codes ALTER COLUMN id SET DEFAULT nextval('public.q_r_codes_id_seq'::regclass);


--
-- TOC entry 4775 (class 2604 OID 17036)
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- TOC entry 5020 (class 0 OID 17213)
-- Dependencies: 246
-- Data for Name: borrow_transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.borrow_transactions (id, item_id, quantity, borrowed_by, status, created_at, updated_at) FROM stdin;
1	39	50	Juan Dela Cruz	borrowed	2025-07-19 11:20:31	2025-07-19 11:20:31
2	39	50	Juan Dela Cruz	borrowed	2025-07-19 11:41:52	2025-07-19 11:41:52
3	39	50	JuanDelacruz	borrowed	2025-07-19 11:42:26	2025-07-19 11:42:26
4	39	10	JuanDelacruz	borrowed	2025-07-19 11:43:05	2025-07-19 11:43:05
5	39	50	JuanDelacruz	borrowed	2025-07-19 11:46:13	2025-07-19 11:46:13
6	39	1	JuanDelacruz	borrowed	2025-07-19 12:41:13	2025-07-19 12:41:13
7	39	2	JuanDelacruz	borrowed	2025-07-19 12:54:50	2025-07-19 12:54:50
\.


--
-- TOC entry 4997 (class 0 OID 17059)
-- Dependencies: 223
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- TOC entry 4998 (class 0 OID 17066)
-- Dependencies: 224
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- TOC entry 5017 (class 0 OID 17188)
-- Dependencies: 243
-- Data for Name: categories; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.categories (id, category, created_at, updated_at) FROM stdin;
1	Desktop	2025-05-26 07:03:02	2025-05-26 07:03:02
2	Desktop	2025-05-26 08:19:30	2025-05-26 08:19:30
3	Consumables	2025-05-26 08:19:30	2025-05-26 08:19:30
4	ICT	2025-05-26 08:19:30	2025-05-26 08:19:30
5	Desktop	2025-05-26 09:50:39	2025-05-26 09:50:39
6	Consumables	2025-05-26 09:50:39	2025-05-26 09:50:39
7	ICT	2025-05-26 09:50:39	2025-05-26 09:50:39
8	Desktop	2025-05-26 12:57:47	2025-05-26 12:57:47
9	Desktop	2025-05-26 13:02:26	2025-05-26 13:02:26
10	Consumables	2025-05-26 13:02:26	2025-05-26 13:02:26
11	ICT	2025-05-26 13:02:26	2025-05-26 13:02:26
12	Supply	2025-05-26 07:03:02	2025-05-26 07:03:02
\.


--
-- TOC entry 5011 (class 0 OID 17129)
-- Dependencies: 237
-- Data for Name: condition_numbers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.condition_numbers (id, condition_number, created_at, updated_at) FROM stdin;
1	A1	2025-05-26 07:03:02	2025-05-26 07:03:02
2	A1	2025-05-26 08:19:30	2025-05-26 08:19:30
3	A2	2025-05-26 08:19:30	2025-05-26 08:19:30
4	A3	2025-05-26 08:19:30	2025-05-26 08:19:30
5	A4	2025-05-26 08:19:30	2025-05-26 08:19:30
6	A5	2025-05-26 08:19:30	2025-05-26 08:19:30
7	A1	2025-05-26 09:50:39	2025-05-26 09:50:39
8	A2	2025-05-26 09:50:39	2025-05-26 09:50:39
9	A3	2025-05-26 09:50:39	2025-05-26 09:50:39
10	A4	2025-05-26 09:50:39	2025-05-26 09:50:39
11	A5	2025-05-26 09:50:39	2025-05-26 09:50:39
12	A1	2025-05-26 12:57:47	2025-05-26 12:57:47
13	A1	2025-05-26 13:02:26	2025-05-26 13:02:26
14	A2	2025-05-26 13:02:26	2025-05-26 13:02:26
15	A3	2025-05-26 13:02:26	2025-05-26 13:02:26
16	A4	2025-05-26 13:02:26	2025-05-26 13:02:26
17	A5	2025-05-26 13:02:26	2025-05-26 13:02:26
\.


--
-- TOC entry 5009 (class 0 OID 17122)
-- Dependencies: 235
-- Data for Name: conditions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.conditions (id, condition, created_at, updated_at) FROM stdin;
1	Serviceable	2025-05-26 07:03:02	2025-05-26 07:03:02
2	Serviceable	2025-05-26 08:19:30	2025-05-26 08:19:30
3	Non - Serviceable	2025-05-26 08:19:30	2025-05-26 08:19:30
4	On Maintenance	2025-05-26 08:19:30	2025-05-26 08:19:30
5	Serviceable	2025-05-26 09:50:39	2025-05-26 09:50:39
6	Non - Serviceable	2025-05-26 09:50:39	2025-05-26 09:50:39
7	On Maintenance	2025-05-26 09:50:39	2025-05-26 09:50:39
8	Serviceable	2025-05-26 12:57:47	2025-05-26 12:57:47
9	Serviceable	2025-05-26 13:02:26	2025-05-26 13:02:26
10	Non - Serviceable	2025-05-26 13:02:26	2025-05-26 13:02:26
11	On Maintenance	2025-05-26 13:02:26	2025-05-26 13:02:26
\.


--
-- TOC entry 5003 (class 0 OID 17091)
-- Dependencies: 229
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- TOC entry 5013 (class 0 OID 17136)
-- Dependencies: 239
-- Data for Name: items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.items (id, uuid, unit, description, pac, unit_value, date_acquired, po_number, category_id, location_id, condition_id, condition_number_id, user_id, image_path, created_at, updated_at, deleted_at, deletion_reason, quantity) FROM stdin;
39	9cb6dc5c-23c6-4ca2-8367-150ea85ccaa3	Flash Drive	panghilo	21223	234.00	2025-07-19	1232	12	14	\N	\N	13	item_images/6JkKmbyPsIapBUZWHuFKMoffRiB5fzctQS21jBsI.jpg	2025-07-19 08:58:48	2025-07-19 12:54:50	\N	\N	37
10	8c1d02ec-2928-45e5-b8e8-74ee3e20828b	hjgjgj	fdgdrg	213213	24344.00	2025-05-27	34234	1	19	10	16	7	item_images/R7Qmj2RukrnqiBjtbjFMho0MHQSE1fsDb8wVmoQm.jpg	2025-05-27 03:15:01	2025-05-27 04:17:06	\N	\N	\N
13	a2041f99-02e4-43c5-a515-ec91961bf4c5	DEsktop	INtel COre	21312421	340000.00	2025-05-14	213143241	8	10	2	3	6	item_images/FKvGNvvc2uXhBxVCH4WUTOF6plORQdiPxh4TXqLi.jpg	2025-05-27 04:41:33	2025-05-27 04:41:33	\N	\N	\N
14	687c38b0-cf70-4740-9273-eba322f0cbdd	Desktop	Intel Core I50	12312331-9897893	343000.00	2025-05-16	21312039034	4	19	1	16	6	item_images/ODvOpC5pHK7oyT4lD6lILntM4It6wL6yEJgfCNPZ.jpg	2025-05-27 05:13:29	2025-05-27 05:13:29	\N	\N	\N
16	e69af3b2-15f2-49ff-8f9e-583fa175e913	uu	ioyi	89	998898.00	2025-05-15	8789798	1	10	10	15	10	\N	2025-05-27 08:22:47	2025-05-27 08:22:47	\N	\N	\N
40	4e0de767-af5e-4c86-95e9-1d836cd54e8e	Randy Orton	Wrestler	123321	2100.00	2025-07-19	3455332	12	14	\N	\N	7	item_images/tqLZu4RbvjwUkaJsWS2U0q2BGuMx2PZp4BcwgH8D.jpg	2025-07-19 10:37:54	2025-07-19 10:37:54	\N	\N	\N
\.


--
-- TOC entry 5018 (class 0 OID 17200)
-- Dependencies: 244
-- Data for Name: items2; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.items2 (id, unit, description) FROM stdin;
\.


--
-- TOC entry 5001 (class 0 OID 17083)
-- Dependencies: 227
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- TOC entry 5000 (class 0 OID 17074)
-- Dependencies: 226
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- TOC entry 5007 (class 0 OID 17115)
-- Dependencies: 233
-- Data for Name: locations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.locations (id, location, created_at, updated_at) FROM stdin;
1	Panabo	2025-05-26 07:03:02	2025-05-26 07:03:02
2	Billing Unit	2025-05-26 08:19:30	2025-05-26 08:19:30
3	Engineering  	2025-05-26 08:19:30	2025-05-26 08:19:30
4	O & M	2025-05-26 08:19:30	2025-05-26 08:19:30
5	COA	2025-05-26 08:19:30	2025-05-26 08:19:30
6	ICT	2025-05-26 08:19:30	2025-05-26 08:19:30
7	Admin & Finance	2025-05-26 08:19:30	2025-05-26 08:19:30
8	IDDD	2025-05-26 08:19:30	2025-05-26 08:19:30
9	Equipment	2025-05-26 08:19:30	2025-05-26 08:19:30
10	Billing Unit	2025-05-26 09:50:39	2025-05-26 09:50:39
11	Engineering  	2025-05-26 09:50:39	2025-05-26 09:50:39
12	O & M	2025-05-26 09:50:39	2025-05-26 09:50:39
13	COA	2025-05-26 09:50:39	2025-05-26 09:50:39
14	ICT	2025-05-26 09:50:39	2025-05-26 09:50:39
15	Admin & Finance	2025-05-26 09:50:39	2025-05-26 09:50:39
16	IDDD	2025-05-26 09:50:39	2025-05-26 09:50:39
17	Equipment	2025-05-26 09:50:39	2025-05-26 09:50:39
18	Panabo	2025-05-26 12:57:47	2025-05-26 12:57:47
19	Billing Unit	2025-05-26 13:02:26	2025-05-26 13:02:26
20	Engineering  	2025-05-26 13:02:26	2025-05-26 13:02:26
21	O & M	2025-05-26 13:02:26	2025-05-26 13:02:26
22	COA	2025-05-26 13:02:26	2025-05-26 13:02:26
23	ICT	2025-05-26 13:02:26	2025-05-26 13:02:26
24	Admin & Finance	2025-05-26 13:02:26	2025-05-26 13:02:26
25	IDDD	2025-05-26 13:02:26	2025-05-26 13:02:26
26	Equipment	2025-05-26 13:02:26	2025-05-26 13:02:26
\.


--
-- TOC entry 4992 (class 0 OID 17026)
-- Dependencies: 218
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2025_05_11_110128_create_personal_access_tokens_table	1
5	2025_05_12_152220_create_locations_table	1
6	2025_05_12_152248_create_conditions_table	1
7	2025_05_12_153716_create_condition_numbers_table	1
8	2025_05_12_153818_create_item_table	1
9	2025_05_14_054700_create_q_r_codes_table	1
10	2025_05_25_101330_add_location_foreign_key_to_users_table	1
11	2025_05_26_001313_create_categories_table	1
12	2025_05_26_010412_add_foreign_key_to_items_table	1
13	2025_05_27_000000_add_soft_deletes_to_items_table	2
14	2025_07_19_101650_add_quantity_to_items_table	2
15	2025_07_19_101739_create_borrow_transactions_table	3
16	2025_07_19_111729_create_borrow_transactions_table	4
\.


--
-- TOC entry 4995 (class 0 OID 17043)
-- Dependencies: 221
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- TOC entry 5005 (class 0 OID 17103)
-- Dependencies: 231
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
1	App\\Models\\User	6	Jikik	a6d1d6d5abeb78ba664276e9ee484de04d3d1a1a3ec88e4f105d86938d96e61e	["*"]	\N	\N	2025-05-27 02:10:42	2025-05-27 02:10:42
2	App\\Models\\User	6	Jikik	d3ae10747bc21b097320109d58ce873c67b6cae23f4ffa352a50101bc3b27601	["*"]	\N	\N	2025-05-27 02:10:43	2025-05-27 02:10:43
3	App\\Models\\User	6	Jikik	64ee15298aad1052eacd4cb3ee1638182166b6572e3cbd8737a4c3ca49dfcf11	["*"]	\N	\N	2025-05-27 02:10:44	2025-05-27 02:10:44
4	App\\Models\\User	3	Jon	771e27e693557fa1e9c86cddb5a63c5a4ded44b789a40a8ad0f6c49a02e2d3f6	["*"]	\N	\N	2025-05-27 02:18:35	2025-05-27 02:18:35
5	App\\Models\\User	6	Jikik	fc612540ce53e52de07ff54ddc10c4d577fcfb7a74e99c83c5f2c6223fad8c3c	["*"]	\N	\N	2025-05-27 02:33:13	2025-05-27 02:33:13
6	App\\Models\\User	6	Jikik	a001498b75627347168ed958a8381139ab3c8c8d8bf9fb79f64d219a4b7b26c3	["*"]	\N	\N	2025-05-27 02:47:26	2025-05-27 02:47:26
7	App\\Models\\User	3	Jon	c586edf9741d1889ea5edf657c17bc4176e45031feb44faf3ba900ab7efc3b86	["*"]	\N	\N	2025-05-27 03:03:59	2025-05-27 03:03:59
8	App\\Models\\User	8	jaspersale	71eb5b05a7a8175350f7b33165b27d361ef758bcdafb65f9c3fcee08d10b73b3	["*"]	\N	\N	2025-05-27 04:23:23	2025-05-27 04:23:23
9	App\\Models\\User	8	jaspersale	cc418ceb911e1dac8ac4be98934b39a5773ed37d7ac55903e7df4f6510970b63	["*"]	\N	\N	2025-05-27 04:26:13	2025-05-27 04:26:13
10	App\\Models\\User	8	jaspersale	1e1c9a1dc95d48f78d3e63833ecd65d8a612a2d30e8779460bb02c10867e7d70	["*"]	\N	\N	2025-05-27 04:35:44	2025-05-27 04:35:44
11	App\\Models\\User	8	jaspersale	e10f05cb75738d7161929bd1afcb4f6f00ce63c29f5bfac2db13b7ef68375f2a	["*"]	\N	\N	2025-05-27 04:41:59	2025-05-27 04:41:59
12	App\\Models\\User	8	jaspersale	2f6868c90113d1e1a727005b6f3c62907261d4bec57c9a74250810d4f533f2c6	["*"]	\N	\N	2025-05-27 05:14:03	2025-05-27 05:14:03
13	App\\Models\\User	8	jaspersale	ff9d2cfbb319e1349e4441de766ea0c42f9cd68cf389e9fbf7aca64caf6377a3	["*"]	\N	\N	2025-05-27 06:07:18	2025-05-27 06:07:18
14	App\\Models\\User	8	jaspersale	ff9d9433a57bcec98d5205a4b6a22a414f7a89be2376ac158d62224ca4e59d27	["*"]	\N	\N	2025-05-27 06:07:20	2025-05-27 06:07:20
15	App\\Models\\User	8	jaspersale	1ef83ea2ab43f1984d3733bb86d7fb6ba5e039b51a03f467d20586e65b2b563e	["*"]	\N	\N	2025-05-27 06:50:18	2025-05-27 06:50:18
16	App\\Models\\User	8	jaspersale	4b67de56e7d19d667881aaa163e771e3d5ca181b26582bfc708ad58bfb2a1f02	["*"]	\N	\N	2025-05-27 07:59:46	2025-05-27 07:59:46
17	App\\Models\\User	8	jaspersale	287ec53f8614e58a467cbb0cc131562083b211ac0fabc84799e966395c7cb5d2	["*"]	\N	\N	2025-05-27 08:16:51	2025-05-27 08:16:51
18	App\\Models\\User	8	jaspersale	1e5a449f4ad8354c4398a5989afcaa6362d8d0bba2d93f85ffbbccd08c7c6fb3	["*"]	\N	\N	2025-05-27 08:20:18	2025-05-27 08:20:18
19	App\\Models\\User	8	jaspersale	5e7e49d74e7bfe54256990572ee370107ad7b22a997a0fb6a671ec02def83a81	["*"]	\N	\N	2025-05-27 08:21:15	2025-05-27 08:21:15
20	App\\Models\\User	8	jaspersale	fe616b028d0c684b66d36f0d2f3d615354b93f8c77f79f66e47a620ed34c312f	["*"]	\N	\N	2025-05-27 08:23:51	2025-05-27 08:23:51
21	App\\Models\\User	8	jaspersale	4af902e54439569b6879c722680b9f66242936210c3f881b89fa54489fe3aed0	["*"]	\N	\N	2025-05-27 08:25:24	2025-05-27 08:25:24
22	App\\Models\\User	8	jaspersale	0a6108e2a81a1551efbc56bb868683e0c8b789e0f3af9c153b895ba2c141f496	["*"]	\N	\N	2025-05-27 08:30:46	2025-05-27 08:30:46
23	App\\Models\\User	8	jaspersale	29360730dbb7fbf391f5f22a9c51262c4c779408be27aac34e0aa24e070f3fea	["*"]	\N	\N	2025-05-27 08:31:20	2025-05-27 08:31:20
24	App\\Models\\User	13	jsale	c2ee9c3bb00259044ca1422c1d3c9d176ac04a8e40a30d86938d7071b80602c1	["*"]	\N	\N	2025-05-27 09:22:29	2025-05-27 09:22:29
25	App\\Models\\User	13	jsale	62fd213fa5106de180d9e4ad15f2102058e9aa088a52a459370d4765c0014b9b	["*"]	\N	\N	2025-05-27 09:22:58	2025-05-27 09:22:58
26	App\\Models\\User	14	Daise	1f457f564671529a4d7b4a2473be6381330061e16620e04d05eac7fa88676c5c	["*"]	\N	\N	2025-07-19 06:40:59	2025-07-19 06:40:59
27	App\\Models\\User	14	Daise	974ad7e762df5da34f987bf2b5df7ad197d04a11d11ea64eee4c42b1613635cc	["*"]	\N	\N	2025-07-19 06:49:01	2025-07-19 06:49:01
28	App\\Models\\User	14	Daise	ca9633238540d726e0538f1bf1d6e9dff36c3ed33d1868dfdc77db2f6fe2bbaa	["*"]	\N	\N	2025-07-19 07:07:22	2025-07-19 07:07:22
29	App\\Models\\User	14	Daise	a56e5b4dbeb0ee0295bfbccf94575e14fe81f29ecadcb5b362f8a4bda7f992e5	["*"]	\N	\N	2025-07-19 07:12:10	2025-07-19 07:12:10
30	App\\Models\\User	15	Demo	a6455216a5a6343d1ab17e9d21f07b0cdb9a9de7f4cb20db971fee4bc0ba1222	["*"]	\N	\N	2025-07-19 07:14:20	2025-07-19 07:14:20
31	App\\Models\\User	15	Demo	03e28e7c6986e0ac02e7c0b73fff555edc0208ee68030557b323fb901076dfe6	["*"]	\N	\N	2025-07-19 07:19:56	2025-07-19 07:19:56
32	App\\Models\\User	15	Demo	16290c43508dbff46598ddaf5d4a646b25372b53fdfba156e89291ac4ae99d6a	["*"]	\N	\N	2025-07-19 07:22:58	2025-07-19 07:22:58
33	App\\Models\\User	15	Demo	c5e054a61775c16da961e581359d388d92223dada2c02abf0da984226989837b	["*"]	\N	\N	2025-07-19 09:12:34	2025-07-19 09:12:34
34	App\\Models\\User	15	Demo	e3c28895555176a78bfd9f633b42808718f0a310b1f8c9c0360c0e861c3a69ae	["*"]	\N	\N	2025-07-19 09:13:24	2025-07-19 09:13:24
35	App\\Models\\User	15	Demo	0fef80fda4145914084abb03992b816e6f8ab811818bcdf357985a424c8fc737	["*"]	\N	\N	2025-07-19 09:14:21	2025-07-19 09:14:21
36	App\\Models\\User	15	Demo	0ab73c3ebd215919682a05479e54afc52b62ad9c7b56cb5bf8ecca5ebd7d6e40	["*"]	\N	\N	2025-07-19 09:22:44	2025-07-19 09:22:44
37	App\\Models\\User	15	Demo	deb9ef40e1bfe364120357fd6c72fff1f67415a7e0c209b7787442f132b46430	["*"]	\N	\N	2025-07-19 09:26:30	2025-07-19 09:26:30
38	App\\Models\\User	15	Demo	4f1320d7ac64f58cb953992fe0b04b267e7053a382629970a7b86166e71c3d15	["*"]	\N	\N	2025-07-19 09:31:46	2025-07-19 09:31:46
39	App\\Models\\User	15	Demo	67958b0e3a70e402daaaf9e806b2418027f2443ab0ba4ca0a913275c874f6572	["*"]	\N	\N	2025-07-19 09:38:48	2025-07-19 09:38:48
40	App\\Models\\User	15	Demo	e011c6dd81a1821a0359f79ef85590abcee754a6a4dae37bbb28ab56c1792a02	["*"]	\N	\N	2025-07-19 09:49:07	2025-07-19 09:49:07
41	App\\Models\\User	15	Demo	84f3b340a1705a5b5f65638c0e7a52de2576fd7fb0c5bf391305598e8c82f43f	["*"]	\N	\N	2025-07-19 10:01:21	2025-07-19 10:01:21
42	App\\Models\\User	15	Demo	7d77fe846d503c9a22b584c664ce09745592a37c237e169889ef7405010017a9	["*"]	\N	\N	2025-07-19 10:38:41	2025-07-19 10:38:41
43	App\\Models\\User	15	Demo	4ad0a35329c875ac1d381d575c4b5257fa876af6a77359bde8ba098a198e204c	["*"]	\N	\N	2025-07-19 11:25:16	2025-07-19 11:25:16
44	App\\Models\\User	15	Demo	c074bf0606753496b0d80cb4d912de21cfadc8f99e5727002d8de63ab31abb88	["*"]	\N	\N	2025-07-19 11:35:43	2025-07-19 11:35:43
45	App\\Models\\User	15	Demo	1aea9c09c5e80879845dd0ed752fce41c6ddeb872119b72bc99ea426b5dae3e3	["*"]	\N	\N	2025-07-19 11:41:49	2025-07-19 11:41:49
46	App\\Models\\User	15	Demo	289752a0913268e982bfcbe48b914785c648976e4af5e4215b514635fdb45f4f	["*"]	\N	\N	2025-07-19 12:23:23	2025-07-19 12:23:23
47	App\\Models\\User	15	Demo	7b32d610932aeebb429c36a95df1277c03e6a09948a4a6bc987a7bb040359c50	["*"]	\N	\N	2025-07-19 12:32:40	2025-07-19 12:32:40
48	App\\Models\\User	15	Demo	f5c7049344ca750b8d1244c72bdf16eb85d567b0c03e966d36ceaa1207b8d8a0	["*"]	\N	\N	2025-07-19 12:34:20	2025-07-19 12:34:20
49	App\\Models\\User	15	Demo	80829a84b171ba6b32175577db465b0d2fb6caa1711aa2d725ffbd33532cbc8e	["*"]	\N	\N	2025-07-19 12:36:20	2025-07-19 12:36:20
50	App\\Models\\User	15	Demo	509800f8bc39298e9bbbddf84a97b7fd7e6d28c084c00933e37460a6341568b9	["*"]	\N	\N	2025-07-19 12:37:20	2025-07-19 12:37:20
51	App\\Models\\User	15	Demo	7180ef0791a6ca57c34e128d4179fdbc51f7ca0972cd305f1b587b3d049e8d74	["*"]	\N	\N	2025-07-19 12:40:10	2025-07-19 12:40:10
52	App\\Models\\User	15	Demo	2ec7716b00fd294ee829a8c6d44b4f4dc0084cc052cb5a13a249854a7b1ebfcf	["*"]	\N	\N	2025-07-19 12:47:27	2025-07-19 12:47:27
53	App\\Models\\User	15	Demo	b41aca5c9a100f06eb8b924cc5882be30c29e10413e7aeb0fc24ac12af6f5002	["*"]	\N	\N	2025-07-19 12:54:27	2025-07-19 12:54:27
\.


--
-- TOC entry 5015 (class 0 OID 17167)
-- Dependencies: 241
-- Data for Name: q_r_codes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.q_r_codes (id, item_id, qr_code_data, image_path, is_active, version, created_at, updated_at) FROM stdin;
8	10	http://127.0.0.1:8000/api/v1/items/8c1d02ec-2928-45e5-b8e8-74ee3e20828b	qrcodes/8c1d02ec-2928-45e5-b8e8-74ee3e20828b.png	t	1	2025-05-27 03:15:02	2025-05-27 03:15:02
11	13	http://127.0.0.1:8000/api/v1/items/a2041f99-02e4-43c5-a515-ec91961bf4c5	qrcodes/a2041f99-02e4-43c5-a515-ec91961bf4c5.png	t	1	2025-05-27 04:41:33	2025-05-27 04:41:33
12	14	http://127.0.0.1:8000/api/v1/items/687c38b0-cf70-4740-9273-eba322f0cbdd	qrcodes/687c38b0-cf70-4740-9273-eba322f0cbdd.png	t	1	2025-05-27 05:13:29	2025-05-27 05:13:29
14	16	http://127.0.0.1:8000/api/v1/items/e69af3b2-15f2-49ff-8f9e-583fa175e913	qrcodes/e69af3b2-15f2-49ff-8f9e-583fa175e913.png	t	1	2025-05-27 08:22:48	2025-05-27 08:22:48
17	39	http://127.0.0.1:8000/api/v1/items/9cb6dc5c-23c6-4ca2-8367-150ea85ccaa3	qrcodes/9cb6dc5c-23c6-4ca2-8367-150ea85ccaa3.png	t	1	2025-07-19 08:58:49	2025-07-19 08:58:49
18	40	http://127.0.0.1:8000/api/v1/items/4e0de767-af5e-4c86-95e9-1d836cd54e8e	qrcodes/4e0de767-af5e-4c86-95e9-1d836cd54e8e.png	t	1	2025-07-19 10:37:54	2025-07-19 10:37:54
\.


--
-- TOC entry 4996 (class 0 OID 17050)
-- Dependencies: 222
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
lnyALMDl9LGu3GbnGXeCzLVLmlKz5eTbNfKDqFye	\N	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	YTozOntzOjY6Il90b2tlbiI7czo0MDoibW1tSFF5T0N5VmlaY1Q2WkQ3aHUyQlJrTnlmM3k1WnU0bGdPTDJTQiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=	1748252613
CkM5fk3gQgePcrIwMftOvrNZilLPlSaJ0ZIwl9Qz	\N	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36	YTozOntzOjY6Il90b2tlbiI7czo0MDoicHdiZkhDTHFlUnFjS2RnaEo3UDQyVkgydEl1S3ZkaHhGYzE5YzJ6MSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=	1748310345
nJLmcy3fkqeZ9jWo0UlmXzpOHNsVFN8WAWqKrl4T	\N	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36	YTozOntzOjY6Il90b2tlbiI7czo0MDoiZVlsczYzck1TTEpaYnNOTWc5Y1lRdHpaNEtaSDJTVnhhUGlDQzZWcCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=	1752907887
\.


--
-- TOC entry 4994 (class 0 OID 17033)
-- Dependencies: 220
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, fullname, username, email, password, role, image, location_id, remember_token, created_at, updated_at) FROM stdin;
6	Jikik	Jikik	jikik2@gmail.com	$2y$12$ee1PCNW/0VNR.Dguf36TbevpUqeXeCX9VQu7RiBQ7Oi8YyZ0taZHu	user	images/XQAdoC0n0wNFvzbVYmRkhxpf5XJJEgRsLHmGlPoj.jpg	12	\N	2025-05-27 02:02:50	2025-05-27 02:02:50
7	Edsil Trinidad	Edsil	edsiltrinidad@gmail.com	$2y$12$4RCami1G8u28XtEB2CUhs.rM3qcQhAEceKnlrldMfIV6bhG6qUpeC	admin	images/8dlsLSkaxz8l9m24KaL0SzMvN6ynk1xQYrNbGsW8.jpg	13	\N	2025-05-27 03:32:18	2025-05-27 03:32:18
10	Jasper Kim Sale	jsprzgrts	jasper2003@gmail.com	$2y$12$lPbeGlYvokZ7J84K2iBqSOQe28kwLoxYj/ug.p5Ta6nqm4nIC78zy	admin	images/MTLefUVZn92dCDz1SdKL4cOHQf7qFuTGeSO9Oeh5.png	14	\N	2025-05-27 05:17:15	2025-05-27 05:17:15
12	jsale	jsale	jsale23@gmail.com	$2y$12$McmvfymxO9yyFvx3eP6QZenR97rYxNbseFllA4loD4nmVMV8ca6vi	user	images/sdgri1pNfvSn51ZQwyQHuDBgeo9dZrgnp1h4rIHv.png	19	\N	2025-05-27 08:58:58	2025-05-27 08:58:58
13	Jasper Kim Sale	jsale	jsale@gmail.com	$2y$12$GqdV/4zxs3eSmDXLp.Apqe57JVlApCBB1fgV.GW4oFwijwMRnGVgK	user	images/8lVd3hleDTTlvG3hWiEru2In6r0FivcpF0nUjbdF.png	15	\N	2025-05-27 09:21:22	2025-05-27 09:21:22
15	Demo Account	Demo	demo@gmail.com	$2y$12$SRfRE/CX9axR0nGuQKvUheCnhmtCqnrRcoFgl4iD74KKt3Owtz3ju	user	images/8jZtPw7304MMBIxT51EDQUBZh15Y6bRdLFn2PNCe.png	14	\N	2025-07-19 07:12:59	2025-07-19 07:12:59
16	Niki Selene	Niki	niki@gmail.com	$2y$12$uhB6hiKcdIJbAdfNd7Sp6O2jl26vLojcBGBCPvmKQWljyPc6QUb8C	user	images/default.png	6	\N	2025-07-19 07:32:15	2025-07-19 07:32:15
\.


--
-- TOC entry 5038 (class 0 OID 0)
-- Dependencies: 245
-- Name: borrow_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.borrow_transactions_id_seq', 7, true);


--
-- TOC entry 5039 (class 0 OID 0)
-- Dependencies: 242
-- Name: categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.categories_id_seq', 12, true);


--
-- TOC entry 5040 (class 0 OID 0)
-- Dependencies: 236
-- Name: condition_numbers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.condition_numbers_id_seq', 17, true);


--
-- TOC entry 5041 (class 0 OID 0)
-- Dependencies: 234
-- Name: conditions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.conditions_id_seq', 11, true);


--
-- TOC entry 5042 (class 0 OID 0)
-- Dependencies: 228
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- TOC entry 5043 (class 0 OID 0)
-- Dependencies: 238
-- Name: items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.items_id_seq', 40, true);


--
-- TOC entry 5044 (class 0 OID 0)
-- Dependencies: 225
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- TOC entry 5045 (class 0 OID 0)
-- Dependencies: 232
-- Name: locations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.locations_id_seq', 26, true);


--
-- TOC entry 5046 (class 0 OID 0)
-- Dependencies: 217
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 16, true);


--
-- TOC entry 5047 (class 0 OID 0)
-- Dependencies: 230
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 53, true);


--
-- TOC entry 5048 (class 0 OID 0)
-- Dependencies: 240
-- Name: q_r_codes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.q_r_codes_id_seq', 18, true);


--
-- TOC entry 5049 (class 0 OID 0)
-- Dependencies: 219
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 16, true);


--
-- TOC entry 4837 (class 2606 OID 17221)
-- Name: borrow_transactions borrow_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.borrow_transactions
    ADD CONSTRAINT borrow_transactions_pkey PRIMARY KEY (id);


--
-- TOC entry 4805 (class 2606 OID 17072)
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- TOC entry 4803 (class 2606 OID 17065)
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- TOC entry 4833 (class 2606 OID 17193)
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- TOC entry 4825 (class 2606 OID 17134)
-- Name: condition_numbers condition_numbers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.condition_numbers
    ADD CONSTRAINT condition_numbers_pkey PRIMARY KEY (id);


--
-- TOC entry 4823 (class 2606 OID 17127)
-- Name: conditions conditions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conditions
    ADD CONSTRAINT conditions_pkey PRIMARY KEY (id);


--
-- TOC entry 4812 (class 2606 OID 17099)
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- TOC entry 4814 (class 2606 OID 17101)
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- TOC entry 4835 (class 2606 OID 17204)
-- Name: items2 items2_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items2
    ADD CONSTRAINT items2_pkey PRIMARY KEY (id);


--
-- TOC entry 4827 (class 2606 OID 17143)
-- Name: items items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_pkey PRIMARY KEY (id);


--
-- TOC entry 4829 (class 2606 OID 17165)
-- Name: items items_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_uuid_unique UNIQUE (uuid);


--
-- TOC entry 4810 (class 2606 OID 17089)
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- TOC entry 4807 (class 2606 OID 17081)
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- TOC entry 4821 (class 2606 OID 17120)
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (id);


--
-- TOC entry 4791 (class 2606 OID 17031)
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- TOC entry 4797 (class 2606 OID 17049)
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- TOC entry 4816 (class 2606 OID 17110)
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- TOC entry 4818 (class 2606 OID 17113)
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- TOC entry 4831 (class 2606 OID 17176)
-- Name: q_r_codes q_r_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.q_r_codes
    ADD CONSTRAINT q_r_codes_pkey PRIMARY KEY (id);


--
-- TOC entry 4800 (class 2606 OID 17056)
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- TOC entry 4793 (class 2606 OID 17042)
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- TOC entry 4795 (class 2606 OID 17040)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 4808 (class 1259 OID 17082)
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- TOC entry 4819 (class 1259 OID 17111)
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- TOC entry 4798 (class 1259 OID 17058)
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- TOC entry 4801 (class 1259 OID 17057)
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- TOC entry 4845 (class 2606 OID 17222)
-- Name: borrow_transactions borrow_transactions_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.borrow_transactions
    ADD CONSTRAINT borrow_transactions_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- TOC entry 4839 (class 2606 OID 17194)
-- Name: items items_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- TOC entry 4840 (class 2606 OID 17149)
-- Name: items items_condition_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_condition_id_foreign FOREIGN KEY (condition_id) REFERENCES public.conditions(id) ON DELETE CASCADE;


--
-- TOC entry 4841 (class 2606 OID 17154)
-- Name: items items_condition_number_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_condition_number_id_foreign FOREIGN KEY (condition_number_id) REFERENCES public.condition_numbers(id) ON DELETE CASCADE;


--
-- TOC entry 4842 (class 2606 OID 17144)
-- Name: items items_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE CASCADE;


--
-- TOC entry 4843 (class 2606 OID 17159)
-- Name: items items_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 4844 (class 2606 OID 17177)
-- Name: q_r_codes q_r_codes_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.q_r_codes
    ADD CONSTRAINT q_r_codes_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- TOC entry 4838 (class 2606 OID 17182)
-- Name: users users_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE CASCADE;


-- Completed on 2025-07-19 21:39:48

--
-- PostgreSQL database dump complete
--

