<?php

namespace App\Http\Controllers\API;

/**
 * @OA\Info(
 *     title="Faydamed API Authentication",
 *     version="1.0.0",
 *     description="Authentication API endpoints for Faydamed system",
 *     @OA\Contact(
 *         email="support@faydamed.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local development server"
 * )
 * 
 * @OA\Server(
 *     url="https://faydaapi.dotprogrammers.com",
 *     description="Development server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
 *     @OA\Property(property="last_name", type="string", maxLength=255, example="Doe"),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
 *     @OA\Property(property="role", type="string", enum={"provider_staff","billing_team","law_firm_staff","supervisor","admin"}, example="provider_staff"),
 *     @OA\Property(property="organization", type="string", maxLength=255, example="Faydamed Healthcare"),
 *     @OA\Property(property="organization_id", type="integer", nullable=true, example=1, description="ID of the organization"),
 *     @OA\Property(property="status", type="string", enum={"active","pending","inactive"}, example="active", description="User's status"),
 *     @OA\Property(property="last_login", type="string", format="date-time", nullable=true, example="2023-01-01T12:00:00Z", description="Last login timestamp"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     title="Organization",
 *     description="Organization model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="org_name", type="string", maxLength=255, example="Faydamed Healthcare"),
 *     @OA\Property(property="org_type", type="string", maxLength=255, example="Healthcare Provider"),
 *     @OA\Property(property="subscription_plan", type="string", maxLength=255, example="Premium"),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="info@faydamed.com"),
 *     @OA\Property(property="no_of_employees", type="integer", example=50),
 *     @OA\Property(property="monthly_revenue", type="number", format="float", example=50000.00),
 *     @OA\Property(property="yearly_revenue", type="number", format="float", example=600000.00),
 *     @OA\Property(property="company_logo", type="string", nullable=true, example="organizations/logos/1699123456_abc123.png"),
 *     @OA\Property(property="company_logo_url", type="string", nullable=true, example="http://localhost:8000/storage/organizations/logos/1699123456_abc123.png"),
 *     @OA\Property(property="tax_bin_no", type="string", nullable=true, example="123456789"),
 *     @OA\Property(property="support_documents", type="array", @OA\Items(type="string"), example={"organizations/documents/1699123456_doc1.pdf", "organizations/documents/1699123457_doc2.pdf"}),
 *     @OA\Property(property="support_documents_urls", type="array", @OA\Items(type="string"), example={"http://localhost:8000/storage/organizations/documents/1699123456_doc1.pdf", "http://localhost:8000/storage/organizations/documents/1699123457_doc2.pdf"}),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="OrganizationType",
 *     type="object",
 *     title="Organization Type",
 *     description="Organization Type model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="type_name", type="string", maxLength=255, example="Healthcare"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Organizations in the healthcare sector providing medical services"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="SubscriptionPlan",
 *     type="object",
 *     title="Subscription Plan",
 *     description="Subscription Plan model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="plan_name", type="string", maxLength=255, example="Premium Plan"),
 *     @OA\Property(property="price", type="number", format="float", example=99.99),
 *     @OA\Property(property="users", type="integer", example=50, description="Number of users allowed"),
 *     @OA\Property(property="features", type="array", @OA\Items(type="string"), example={"Advanced Analytics", "Priority Support", "Custom Branding", "API Access"}),
 *     @OA\Property(property="organizations", type="integer", example=5, description="Number of organizations allowed"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="Blog",
 *     type="object",
 *     title="Blog",
 *     description="Blog post model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", maxLength=255, example="Introduction to Laravel"),
 *     @OA\Property(property="url_slug", type="string", maxLength=255, example="introduction-to-laravel-1699456789"),
 *     @OA\Property(property="author", type="string", maxLength=255, example="John Doe"),
 *     @OA\Property(property="category", type="string", maxLength=255, example="Technology"),
 *     @OA\Property(property="excerpt", type="string", nullable=true, example="A brief introduction to Laravel framework and its features"),
 *     @OA\Property(property="content", type="string", example="Laravel is a powerful PHP framework that makes web development easier..."),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), nullable=true, example={"laravel", "php", "framework", "web development"}),
 *     @OA\Property(property="status", type="string", enum={"draft", "published", "schedule"}, example="published"),
 *     @OA\Property(property="blog_images", type="array", @OA\Items(type="string"), nullable=true, example={"blogs/images/1699456789_abc123.jpg", "blogs/images/1699456790_def456.jpg"}),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true, example="2025-11-08T10:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-08T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-08T00:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="BlogCategory",
 *     type="object",
 *     title="Blog Category",
 *     description="Blog Category model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="category", type="string", maxLength=255, example="Technology"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Articles about technology, programming, and innovation"),
 *     @OA\Property(property="post", type="integer", example=15, description="Number of posts in this category"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-09T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-09T00:00:00Z")
 * )
 */
class OpenApiDefinition
{
    // This class is only for OpenAPI documentation definitions
}