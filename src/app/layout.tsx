import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { Toaster } from "sonner";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "BusinessVance - Professional Business Reports & Advisory Services",
  description:
    "Professional business reports and advisory services to help you make confident, informed decisions. Insight. Strategy. Success.",
  keywords: [
    "BusinessVance",
    "business reports",
    "advisory services",
    "business consulting",
    "South Africa",
  ],
  authors: [{ name: "BusinessVance" }],
  openGraph: {
    title: "BusinessVance - Professional Business Reports & Advisory Services",
    description:
      "Professional business reports and advisory services to help you make confident, informed decisions.",
    siteName: "BusinessVance",
    type: "website",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased bg-background text-foreground`}
      >
        {children}
        <Toaster position="top-right" richColors />
      </body>
    </html>
  );
}